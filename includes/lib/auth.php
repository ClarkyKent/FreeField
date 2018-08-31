<?php
/*
    This library file contains functions relating to authentication, users,
    groups and permissions.
*/

__require("config");
__require("db");

/*
    The /includes/sessionkey.php file contains a randomly generated key used to
    encrypt session data stored in browser cookies. This file is generated upon
    first installation.
*/
require_once(__DIR__."/../sessionkey.php");

class Auth {
    /*
        Cache for the current authenticated user to prevent unnecessary database
        lookups due to repeat calls to `getCurrentUser()`.
    */
    private static $authSessionCache = null;
    /*
        Cache for available permission groups, for the same purpose.
    */
    private static $groupsCache = null;

    /*
        Returns an array of authentication providers and their configuration
        settings that must be set in order for the provider to work. (For
        example, Discord authentication requires that both client ID and client
        secret is set.)
    */
    private static function getProviderRequirements() {
        return array(
            "discord" => ["client-id", "client-secret"],
            "telegram" => ["bot-username", "bot-token"]
        );
    }

    /*
        Returns whether the given authentication provider has been enabled, and
        whether all of the options for the given provider in
        `getConfigRequirements()` are validly defined in the configuration file.
    */
    public static function isProviderEnabled($provider) {
        $providerRequirements = self::getProviderRequirements();

        // Undefined authentication provider
        if (!isset($providerRequirements[$provider])) return false;
        // Authentication provider exists, but is not enabled
        if (!Config::get("auth/provider/{$provider}/enabled")) return false;

        /*
            Create an array of required configuration settings for the given
            authentication provider. All of these are empty strings by default.
            This means that if any of the settings in this array resolve to an
            empty string, that setting is not set, hence the provider script
            will fail to work, thus the provider should be considered disabled.
        */
        $conf = array();
        foreach ($providerRequirements[$provider] as $req) {
            $conf[] = "auth/provider/{$provider}/{$req}";
        }
        if (Config::ifAny($conf, "")) return false;

        return true;
    }

    /*
        Returns a list of all available authentication providers, including
        disabled ones.
    */
    public static function getAllProviders() {
        return array_keys(self::getProviderRequirements());
    }

    /*
        Returns a list of all enabled authentication providers.
    */
    public static function getEnabledProviders() {
        $providers = self::getAllProviders();
        for ($i = 0; $i < count($providers); $i++) {
            if (!self::isProviderEnabled($providers[$i])) {
                unset($providers[$i]);
            }
        }
        return $providers;
    }

    /*
        Gets the User-Agent without version numbers for identifying a specific
        browser type. This string is used to prevent session hijacking by
        limiting the validity of a session to a specific browser. The function
        removes version numbers from the string, meaning browser and system
        updates won't invalidate the session.
    */
    private static function getVersionlessUserAgent() {
        if (!isset($_SERVER["HTTP_USER_AGENT"])) return "";
        return preg_replace('@/[^ ]+@', "", $_SERVER["HTTP_USER_AGENT"]);
    }

    /*
        Fetches and decrypts the raw, unauthenticated session array from cookie
        data supplied by the browser. This will be further processed in other
        functions.
    */
    private static function getSession() {
        if (!isset($_COOKIE["session"])) return null;
        $session = $_COOKIE["session"];

        $c = base64_decode($session, true);
        if ($c === false) return null;

        $ivlen = openssl_cipher_iv_length("AES-256-CBC");
        if (strlen($c) < $ivlen + 32 + 1) return null;

        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, 32);
        $ciph = substr($c, $ivlen + 32);

        $data = openssl_decrypt(
            $ciph,
            "AES-256-CBC",
            AuthSession::getSessionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($data === false) return null;

        return json_decode($data, true);
    }

    /*
        Writes authenticated session and validation data to a cookie. Called
        from the authentication providers when they have passed authentication
        stage III.
    */
    public static function setAuthenticatedSession($id, $expire, $humanId, $suggestedNick) {
        $db = Database::getSparrow();
        $user = $db
            ->from(Database::getTable("user"))
            ->where("id", $id)
            ->select(array("token", "approved"))
            ->one();

        /*
            If there is no token, that means that the user is registering a new
            account on FreeField.
        */
        if ($user === null || count($user) <= 0) {
            /*
                If approval is required by the admins, the account should be
                flagged as "pending approval". The registering user will be
                given the same privileges as anonymous visitors until their
                account has been appoved.
            */
            $approved = !Config::get("security/require-validation");
            /*
                The token is used to invalidate sessions. The cookie array
                contains a "token" value that must match the token value stored
                in the database. If they do not match, the session is considered
                invalid. This makes global session invalidation easy - simply
                generate a new token, and all existing sessions for that user
                will immediately be considered invalid due to the token
                mismatch.
            */
            $token = substr(base64_encode(openssl_random_pseudo_bytes(32)), 0, 32);

            $data = array(
                "id" => $id,
                "provider_id" => $humanId,
                "nick" => $suggestedNick,
                "token" => $token,
                "permission" => Config::get("permissions/default-level"),
                "approved" => ($approved ? 1 : 0)
            );
            $db
                ->from(Database::getTable("user"))
                ->insert($data)
                ->execute();
        } else {
            /*
                If approval is required by the admins, the account should be
                flagged as "pending approval". The user has the same privileges
                as anonymous visitors until their account has been appoved.
                Setting his boolean
            */
            $approved = $user["approved"];
            $token = $user["token"];
        }

        /*
            This is the array that is stored in clients' session cookie. It
            identifies the logged in user, has the current user token as of the
            time of login, and a session expiration date.

            Storing the expiration date in the cookie rather than the creation
            date ensures that the session is valid for the given period of time
            specified by the session length setting in the security settings
            page on the administration pages, even if that value is later
            changed to a shorter duration that would otherwise invalidate it.

            Another way to implement this would be to store the creation date
            instead of the expiry date. This would cause all sessions that were
            created before the oldest allowed date to be invalid if the session
            length is changed to a shorter period of time. It would also cause
            problems with "dormant sessions" which are currently invalid because
            they have expired, but would suddenly become valid again if the
            session length is later updated to a longer time frame.

            The method used (expiry date vs creation date) might change or
            become changeable by administrators in a later update.
        */
        $session = array(
            "id" => $id,
            "token" => $token,
            "expire" => time() + $expire
        );

        /*
            As an additional security feature, sessions can be restricted to
            only be valid for a particular user-agent or language. This prevents
            session hijacking attacks where an attacker steals the session
            cookie and uses it on a machine running a different browser or
            system language.
        */
        if (Config::get("security/validate-ua")) {
            $session["http-ua"] = self::getVersionlessUserAgent();
        }
        if (Config::get("security/validate-lang")) {
            $session["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])
                                    ? $_SERVER["HTTP_ACCEPT_LANGUAGE"]
                                    : "";
        }

        self::setSession($session, $expire);
        return $approved;
    }

    /*
        This function is the opposite of `getCookie()`. It takes a session data
        array, encrypts it, and puts it in a cookie on the client's browser.
    */
    private static function setSession($data, $expire) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("AES-256-CBC"));
        $ciph = openssl_encrypt(
            json_encode($data),
            "AES-256-CBC",
            AuthSession::getSessionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );
        $hmac = hash_hmac("SHA256", $ciph, AuthSession::getSessionKey(), true);
        $session = base64_encode($iv.$hmac.$ciph);
        setcookie("session", $session, time() + $expire, "/");
    }

    /*
        Finds a user with the given user ID in the database and constructs a
        `User` instance for extracting information about that user.
    */
    public static function getUser($id) {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $session["id"])
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->one();

        return new User($userdata);
    }

    /*
        Fetches a list of all registered users from the database and returns an
        array of `User` instances used for extracting information about these
        users.
    */
    public static function listUsers() {
        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->many();

        $users = array();
        foreach ($userdata as $data) {
            $users[] = new User($data);
        }
        return $users;
    }

    /*
        Authenticates the current cookie session data against the user database.
    */
    public static function getCurrentUser() {
        /*
            To avoid repeat database lookups resulting from repeatedly calling
            this function, we'll check if the results have been cached first,
            and if so, return the cached user.
        */
        if (self::$authSessionCache !== null) return self::$authSessionCache;

        /*
            Get the session cookie. If there is no session cookie, or the
            session has expired, the current user is `null` user (i.e.
            unauthenticated).
        */
        $session = self::getSession();
        if ($session === null) return self::setReturnUser(new User(null));
        if ($session["expire"] < time()) return self::setReturnUser(new User(null));

        /*
            Do additional validation of the user agent and/or browser language
            if the site admins have requested this for additional session
            security.
        */
        $selectors = array();
        if (Config::get("security/validate-ua")) {
            $selectors["http-ua"] = self::getVersionlessUserAgent();
        }
        if (Config::get("security/validate-lang")) {
            $selectors["http-lang"] = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])
                                      ? $_SERVER["HTTP_ACCEPT_LANGUAGE"]
                                      : "";
        }
        foreach ($selectors as $selector => $expectedValue) {
            if ($session[$selector] != $expectedValue) return self::setReturnUser(new User(null));
        }

        $db = Database::getSparrow();
        $userdata = $db
            ->from(Database::getTable("user"))
            ->where("id", $session["id"])
            ->where("token", $session["token"])
            ->leftJoin(Database::getTable("group"), array(
                Database::getTable("group").".level" => Database::getTable("user").".permission"
             ))
            ->one();

        /*
            Create a `User` instance, cache it for future lookups, and return
            it.
        */
        return self::setReturnUser(new User($userdata));
    }

    /*
        This function takes an input `User` instance, assigns it to the
        authenticated user object cache, and returns the same instance. This is
        used so that in `getCurrentUser()`, we can write:

            return self::setReturnUser(new User($userdata));

        rather than:

            $user = new User($userdata);
            self::$authSessionCache = $user;
            return $user;
    */
    private static function setReturnUser($user) {
        self::$authSessionCache = $user;
        return $user;
    }

    /*
        Returns whether or not the user is logged in. If the current user is the
        `null` user (i.e. unauthenticated), the `User::exists()` function always
        returns false, while it always returns true if the corresponding user is
        valid and signed in with a session that has not expired.
    */
    public static function isAuthenticated() {
        return self::getCurrentUser()->exists();
    }

    /*
        Get a list of available groups from the database.

        Each group is stored in a database with the following structure:

          - `group_id` INT
          - `level` SMALLINT
          - `label` VARCHAR(64)
          - `color` CHAR(6)

        That same structure is the structure returned by this function.
    */
    public static function listPermissionLevels() {
        /*
            To avoid repeat database lookups resulting from repeatedly calling
            this function, we'll check if the results have been cached first,
            and if so, return the cached groups list.
        */
        if (self::$groupsCache !== null) return self::$groupsCache;

        $db = Database::getSparrow();
        $perms = $db
            ->from(Database::getTable("group"))
            ->many();

        /*
            The groups list is sorted in order of descending permission levels.
            This means that higher ranked groups appear first in this array.
        */
        usort($perms, function($a, $b) {
            if ($a["level"] == $b["level"]) return 0;
            return $a["level"] > $b["level"] ? -1 : 1;
        });

        /*
            Cache the groups list for future lookups.
        */
        self::$groupsCache = $perms;
        return $perms;
    }

    /*
        Groups may have label names containing I18N tokens. This enables
        FreeField to use a single label string for all supported languages. For
        example, the group with the label "Administrator" has the name/label
        "{i18n:group.level.admin}". This will display the group with the label
        "Administrators" to English users and whatever the user's localization
        of "Administrators" is if they use a different language. If the
        name/label of the group was configured as the string "Administrators"
        instead, the group would be named the English word "Administrators"
        regardless of the localization used by visiting users. The format of
        the permission label I18N token replacement strings is:

            {i18n:<i18n_token>}

        This means that e.g. "{i18n:group.level.admin}" would be substituted
        with the localization found for the I18N key "group.level.admin" for the
        current language in the localization files.
    */
    public static function resolvePermissionLabelI18N($label) {
        if (substr($label, 0, 6) == "{i18n:" && substr($label, -1, 1) == "}") {
            __require("i18n");
            $query = substr($label, 6, -1);
            return I18N::resolve($query);
        } else {
            return $label;
        }
    }

    /*
        This is a wrapper for `resolvePermissionLabelI18N()` which ensures that
        its output is suitable for outputting directly into an HTML document. It
        escapes special characters to avoid XSS attacks.
    */
    public static function resolvePermissionLabelI18NHTML($label) {
        return htmlspecialchars(self::resolvePermissionLabelI18N($label), ENT_QUOTES);
    }

    /*
        Returns a <select> element that can be used to select a group on the
        administration pages. This function is called from
        `PermissionOption::getControl()` in /includes/config/types.php. It takes
        parameters for the HTML attributes to apply to the <select> element, as
        well as the current permission level selected. The select box will have
        the current level pre-selected.
    */
    public static function getPermissionSelector($name = null, $id = null, $selectedLevel = 0) {
        $user = self::getCurrentUser();
        $perms = self::listPermissionLevels();
        $opts = "";

        // Current group
        $curperm = null;

        /*
            Loop over all available groups and check if its permission level
            equals the currently selected level for the control. Add the group
            to the options list as an HTML node. The current group, if found, is
            set aside, as we'll be adding a separate entry in the drop-down for
            that group in addition to its entry in the list of all groups.
        */
        foreach ($perms as $perm) {
            if ($perm["level"] == $selectedLevel) $curperm = $perm;
            $opts .= '<option value="'.$perm["level"].'"'.
                              ($perm["color"] !== null ? ' style="color: #'.$perm["color"].'"' : '').
                              ($user->canChangeAtPermission($perm["level"]) ? '' : ' disabled').'>'.
                                    $perm["level"].
                                    ' - '.
                                    self::resolvePermissionLabelI18NHTML($perm["label"]).
                     '</option>';
        }
        /*
            Add the currently selected group to a separate <optgroup> labeled
            "Current group" at the top of the drop-down. If the currently
            selected group doesn't exist (it has been removed), a temporary
            group is created labeled "Unknown" that will retain the permission
            level currently selected, even though no group for it exists in the
            database.
        */
        if ($curperm === null) {
            $curopt = '<option value="'.$selectedLevel.'" selected>'.
                            $selectedLevel.
                            ' - '.
                            self::resolvePermissionLabelI18NHTML("{i18n:group.level.unknown}").
                      '</option>';
        } else {
            $curopt = '<option value="'.$selectedLevel.'" style="color:" selected>'.
                            $selectedLevel.
                            ' - '.
                            self::resolvePermissionLabelI18NHTML($curperm["label"]).
                      '</option>';
        }

        return '<select'.($name !== null ? ' name="'.$name.'"' : '').
                         ($id !== null ? ' id="'.$id.'"' : '').
                         ($user->canChangeAtPermission($selectedLevel) ? '' : ' disabled').'>
                                <optgroup label="'.I18N::resolveHTML("group.selector.current").'">
                                    '.$curopt.'
                                </optgroup>
                                <optgroup label="'.I18N::resolveHTML("group.selector.available").'">
                                    '.$opts.'
                                </optgroup>
                </select>';
    }
}

/*
    This class contains functions to get information about a particular user. It
    is constructed from and returned by `Auth::getUser()`.
*/
class User {
    /*
        User data array passed from `Auth::getUser()`.
    */
    private $data = null;

    function __construct($userdata) {
        $this->data = $userdata;
    }

    /*
        Gets whether or not the user exists. Anonymous users and users with an
        invalid session will have `null` assigned to `$this->data`. Therefore,
        we can just check whether that variable is null (and whether the user
        array actually contains any data) to see if the user exists.
    */
    public function exists() {
        return $this->data !== null && count($this->data > 0);
    }

    /*
        Gets the nickname of the current user. This function is not HTML safe
        and may result in XSS attacks if not properly filtered before being
        output to a page.
    */
    public function getNickname() {
        if (!$this->exists()) return "<Anonymous>";
        return $this->data["nick"];
    }

    /*
        An HTML safe version of `getNickname()`. This function additionaly
        styles the nickname with the color of the group that the user is a
        member of.
    */
    public function getNicknameHTML() {
        if (!$this->exists()) return htmlspecialchars("<Anonymous>", ENT_QUOTES);
        $color = self::getColor();
        return '<span'.($color !== null ? ' style="color: #'.$color.';"' : '').'>'.
                    htmlspecialchars(self::getNickname(), ENT_QUOTES).
               '</span>';
    }

    /*
        Returns the provider identity (human readable ID as provided by the
        user's authentication provider) of the user. This can be e.g.
        "Username#1234" if the user is authenticated with Discord. This function
        is not HTML safe and may result in XSS attacks if not properly filtered
        before being output to a page.
    */
    public function getProviderIdentity() {
        if (!$this->exists()) return "<Anonymous>";
        return $this->data["provider_id"];
    }

    /*
        An HTML safe version of `getProviderIdentity()`. This function
        additionally prepends the logo of the authentication provider used by
        the user to the provider identity.
    */
    public function getProviderIdentityHTML() {
        $providerIcons = array(
            "discord" => "discord",
            "telegram" => "telegram-plane"
        );
        return '<span>
                    <i class="
                        auth-provider-'.$this->getProvider().'
                        fab
                        fa-'.$providerIcons[$this->getProvider()].'">
                    </i> '.htmlspecialchars($this->getProviderIdentity(), ENT_QUOTES).'
                </span>';
    }

    /*
        Gets the ID of the user in the form <provider>:<id>, where `provider` is
        the authentication provider used by the user (e.g. "discord") and `id`
        is the internal ID of the user at the provider. Note that this ID is not
        the same as the provider identity - whereas the provider identity is
        usually the username of the user, the ID is a unique, permanent
        identifier internally used by the provider to identify users, and does
        not change even if the provider identity changes.
    */
    public function getUserID() {
        if (!$this->exists()) return null;
        return $this->data["id"];
    }

    /*
        Gets the current group membership of the user as a numerical permission
        level value.
    */
    public function getPermissionLevel() {
        /*
            Anonymous/unauthenticated/unapproved users default to 0.
        */
        if (!$this->exists()) return 0;

        $perm = $this->data["permission"];

        /*
            Group permission levels may temporarily be set to 1000 higher than
            their intended value while the permission level of a group is being
            changed by an administrator. This is done to prevent collisions and
            deadlocks when updating the database, but could theoretically cause
            a privilege escalation attack vector while this update is being
            processed. To prevent this, permission values higher than 1000 are
            reset to their original value, so the permission level of the user
            in practice stays the same the whole time.

            More information on why this is necessary is commented in detail in
            /admin/apply-groups.php.
        */
        return $perm > 1000 ? $perm - 1000 : $perm;
    }

    /*
        Gets the color of the group this user is a member of.
    */
    public function getColor() {
        if (!$this->exists()) return 0;
        return $this->data["color"];
    }

    /*
        Gets the date of the first login from this user in "YYYY-mm-dd HH:ii:ss"
        format.
    */
    public function getRegistrationDate() {
        if (!$this->exists()) return null;
        return $this->data["user_signup"];
    }

    /*
        Gets the authentication provider used by this user.
    */
    public function getProvider() {
        if (!$this->exists()) return null;
        if (strpos($this->data["id"], ":") !== false) {
            return substr($this->data["id"], 0, strpos($this->data["id"], ":"));
        } else {
            return null;
        }
    }

    /*
        Checks whether the user has been approved by an administrator. This
        defaults to false for new members if the site requires manual user
        account approval, and true otherwise.
    */
    public function isApproved() {
        return $this->data["approved"];
    }

    /*
        Checks whether the user has the given permission, or any permission in
        a set of permissions. Valid inputs are one permission, any one of a set,
        or all of a set. Examples:

        One permission:
            "admin/groups/general"

        Any one or more of a set:
            "admin/?/general"
            (matches if the user is granted the "general" permission under any
            subkey of "admin")

        All of a set:
            "admin/<*>/general"
            (replace <*> with * - I can't place * there or I'll accidentally
            end the PHP comment due to the forward slashes)
            (returns true only if the user has been granted the "general"
            permission on all subkeys of "admin")
    */
    public function hasPermission($permission) {
        if (!$this->exists()) {
            // TODO: Permission overries

            /*$explperms = explode(",", $this->data["overrides"]);
            foreach ($explperms as $perm) {
                if (substr($perm, 1) == $permission) {
                    if (substr($perm, 0, 1) == "+") return true;
                    if (substr($perm, 0, 1) == "-") return false;
                }
            }*/
        }

        /*
            Get the current permission level of the user.
        */
        $userperm = (
            $this->data === null || !self::isApproved()
            ? 0
            : $this->getPermissionLevel()
        );

        if (strpos($permission, "?") !== FALSE) {
            // Match any permission in set
            $root = trim(strtok($permission, "?"), "/");
            $tree = Config::get("permissions/level/{$root}");
            $tail = trim(substr($permission, strpos($permission, "?") + 1), "/");

            foreach ($tree as $domain => $sections) {
                foreach ($sections as $section => $perm) {
                    if ($section !== $tail) continue;
                    if ($userperm >= $perm) return true;
                }
            }

            return false;
        } elseif (strpos($permission, "*") !== FALSE) {
            // Match all permissions in set
            $root = trim(strtok($permission, "?"), "/");
            $tree = Config::get("permissions/level/{$root}");
            $tail = trim(substr($permission, strpos($permission, "?") + 1), "/");

            foreach ($tree as $domain => $sections) {
                foreach ($sections as $section => $perm) {
                    if ($section !== $tail) continue;
                    if ($userperm < $perm) return false;
                }
            }

            return true;
        } else {
            // Match specific permission
            $perm = Config::get("permissions/level/{$permission}");
            return $userperm >= $perm;
        }
    }

    /*
        Checks whether the current user is authorized to make changes to an
        object that requires the given permission level. E.g. if the current
        user is permission level 80, and they try to change a group or user's
        permission level to 120, this function is called with 120 as its
        argument, and will return false, since it is higher than or equal to the
        current user's permission level. This is implemented to prevent
        privilege escalation attacks.
    */
    public function canChangeAtPermission($level) {
        if ($level < $this->getPermissionLevel()) {
            return true;
        }
        /*
            If the user has the "admin/groups/self-manage" permission, they are
            permitted to change the settings of users and groups at the same
            level as themselves. This is to ensure the "site host" group can
            make changes to their own group, since there is no group higher than
            themselves that they can consult for making changes on their behalf.

            If this was not implemented, permission settings currently set to
            the "site host" would not be possible to change to a lower value
            without manually editing the config JSON.
        */
        if (
            $this->hasPermission("admin/groups/self-manage") &&
            $level <= $this->getPermissionLevel()
        ) {
            return true;
        }
        return false;
    }

    /*
        Administrators can manually override the permissions for a single user
        of a group. This function checks whether such an override is in place
        for the given permission.
    */
    public function hasExplicitRights($permission) {
        // TODO: Implement
        /*if (!$this->exists()) return false;
        $explperms = explode(",", $this->data["overrides"]);
        foreach ($explperms as $perm) {
            if (substr($perm, 1) == $permission) return true;
        }*/
        return false;
    }
}

?>
