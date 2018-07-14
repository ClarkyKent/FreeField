<?php

require_once("./includes/lib/global.php");
__require("auth");
__require("i18n");

if (!Auth::getCurrentUser()->hasPermission("access")) {
    if (!Auth::isAuthenticated()) {
        header("HTTP/1.1 307 Temporary Redirect");
        header("Location: ".Config::getEndpointUri("/auth/login.php"));
        exit;
    } else {
        ?>
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <meta name="robots" content="noindex,nofollow">
                    <title><?php echo Config::get("site/name"); ?> | <?php echo I18N::resolve("login.title"); ?></title>
                    <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
                    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
                    <link rel="stylesheet" href="./css/main.css">
                    <link rel="stylesheet" href="./css/<?php echo Config::get("themes/color/user-settings/theme"); ?>.css">

                    <!--[if lte IE 8]>
                        <link rel="stylesheet" href="./css/layouts/side-menu-old-ie.css">
                    <![endif]-->
                    <!--[if gt IE 8]><!-->
                        <link rel="stylesheet" href="./css/layouts/side-menu.css">
                    <!--<![endif]-->
                </head>
                <body>
                    <div id="main">
                        <div class="header" style="border-bottom: none; margin-bottom: 50px;">
                            <h1 class="red"><?php echo I18N::resolve("access_denied.title"); ?></h1>
                            <h2><?php echo I18N::resolve("access_denied.desc"); ?></h2>
                        </div>

                        <div class="content">
                            <p><?php echo I18N::resolveArgs("access_denied.info", Auth::getCurrentUser()->getProviderIdentity()); ?></p>
                        </div>
                    </div>
                    <script src="./js/ui.js"></script>
                </body>
            </html>
        <?php
        exit;
    }
}

__require("config");
__require("theme");
__require("research");

$provider = Config::get("map/provider/source");

?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
        <meta name="robots" content="noindex,nofollow">
        <title><?php echo Config::get("site/name"); ?></title>
        <script src="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <script src="./js/clientside-i18n.php"></script>
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v0.46.0/mapbox-gl.css">
        <link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.13/css/all.css" integrity="sha384-DNOHZ68U8hZfKXOrtjWvjxusGo9WQnrNx2sqG0tfsghAvtVlRW3tvkXWZh58N9jp" crossorigin="anonymous">
        <link rel="stylesheet" href="./css/main.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" href="./css/map-markers.php">

        <!--[if lte IE 8]>
            <link rel="stylesheet" href="./css/layouts/side-menu-old-ie.css">
        <![endif]-->
        <!--[if gt IE 8]><!-->
            <link rel="stylesheet" href="./css/layouts/side-menu.css">
        <!--<![endif]-->
    </head>
    <body>
        <div id="layout">
            <!-- Menu toggle -->
            <a href="#menu" id="menuLink" class="menu-link">
                <!-- Hamburger icon -->
                <span></span>
            </a>

            <div id="menu">
                <div class="pure-menu">
                    <a class="pure-menu-heading" href=".">FreeField</a>

                    <ul class="pure-menu-list">
                        <?php if (Auth::isAuthenticated()) { ?>
                            <div class="menu-user-box">
                                <span class="user-box-small"><?php echo I18N::resolve("sidebar.signed_in_as"); ?></span><br>
                                <span class="user-box-nick"><?php echo Auth::getCurrentUser()->getNicknameHTML(); ?></span><br />
                                <span class="user-box-small"><?php echo Auth::getCurrentUser()->getProviderIdentityHTML(); ?></span><br>
                            </div>
                            <li class="pure-menu-item"><a href="./auth/logout.php" class="pure-menu-link"><i class="menu-fas fas fa-sign-in-alt"></i> <?php echo I18N::resolve("sidebar.logout"); ?></a></li>
                        <?php } else { ?>
                            <li class="pure-menu-item"><a href="./auth/login.php" class="pure-menu-link"><i class="menu-fas fas fa-sign-in-alt"></i> <?php echo I18N::resolve("sidebar.login"); ?></a></li>
                        <?php } ?>
                        <div class="menu-spacer"></div>
                        <div id="map-menu">
                            <?php if (Auth::getCurrentUser()->hasPermission("submit-poi")) { ?>
                                <li class="pure-menu-item"><a href="#" id="add-poi-start" class="pure-menu-link"><i class="menu-fas fas fa-plus"></i> <?php echo I18N::resolve("sidebar.add_poi"); ?></a></li>
                            <?php } ?>
                            <li class="pure-menu-item"><a href="#" id="menu-open-settings" class="pure-menu-link"><i class="menu-fas fas fa-wrench"></i> <?php echo I18N::resolve("sidebar.settings"); ?></a></li>
                            <?php if (Auth::getCurrentUser()->hasPermission("admin/?/general")) { ?>
                                <li class="pure-menu-item"><a href="./admin/" class="pure-menu-link"><i class="menu-fas fas fa-angle-double-right"></i> <?php echo I18N::resolve("sidebar.manage_site"); ?></a></li>
                            <?php } ?>
                        </div>
                        <div id="settings-menu" class="hidden-by-default">
                            <li class="pure-menu-item"><a href="#" id="menu-reset-settings" class="pure-menu-link"><i class="menu-fas fas fa-undo"></i> <?php echo I18N::resolve("sidebar.reset"); ?></a></li>
                            <li class="pure-menu-item"><a href="#" id="menu-close-settings" class="pure-menu-link"><i class="menu-fas fas fa-angle-double-left"></i> <?php echo I18N::resolve("sidebar.cancel"); ?></a></li>
                        </div>
                    </ul>
                </div>
            </div>

            <div id="main">
                <div id="map-container">
                <div id="dynamic-banner-container">

                </div>
                <div id="add-poi-banner" class="banner">
                    <div class="banner-inner">
                        <?php echo I18N::resolveArgs("poi.add.instructions", '<a href="#" id="add-poi-cancel-banner">', '</a>'); ?>
                    </div>
                </div>
                <div id="poi-details" class="cover-box">
                    <div class="cover-box-inner">
                        <div class="header">
                            <h1 id="poi-name" class="head-small"></h1>
                        </div>
                        <div class="cover-box-content content">
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align"><img id="poi-objective-icon" src="about:blank" class="bigmarker"></div>
                                <div class="pure-u-1-2"><img id="poi-reward-icon" src="about:blank" class="bigmarker"></div>
                            </div>
                            <p class="centered"><?php echo I18N::resolveArgs("poi.objective_text", '<strong id="poi-objective" class="strong-color"></strong>', '<strong id="poi-reward" class="strong-color"></strong>'); ?></p>
                            <div class="cover-button-spacer"></div>
                            <div class="pure-g">
                                <div class="pure-u-1-1 right-align"><span type="button" id="poi-add-report" class="button-standard split-button"><?php echo I18N::resolve("poi.report_research"); ?></span></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align"><span type="button" id="poi-directions" class="button-standard split-button button-spaced left"><?php echo I18N::resolve("poi.directions"); ?></span></div>
                                <div class="pure-u-1-2"><span type="button" id="poi-close" class="button-standard split-button button-spaced right"><?php echo I18N::resolve("ui.button.close"); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="add-poi-details" class="cover-box">
                    <div class="cover-box-inner">
                        <div class="header">
                            <h1><?php echo I18N::resolve("poi.add.title"); ?></h1>
                        </div>
                        <div class="cover-box-content content pure-form">
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("poi.add.name"); ?>:</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" id="add-poi-name"></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("poi.add.latitude"); ?>:</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" id="add-poi-lat" readonly></p></div>
                            </div>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("poi.add.longitude"); ?>:</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" id="add-poi-lon" readonly></p></div>
                            </div>
                            <div class="cover-button-spacer"></div>
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align"><span type="button" id="add-poi-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolve("ui.button.cancel"); ?></span></div>
                                <div class="pure-u-1-2"><span type="button" id="add-poi-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolve("poi.add.submit"); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="update-poi-details" class="cover-box">
                    <div class="cover-box-inner">
                        <div class="header">
                            <h1><?php echo I18N::resolve("poi.update.title"); ?></h1>
                        </div>
                        <div class="cover-box-content content pure-form">
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("poi.update.name"); ?>:</p></div>
                                <div class="pure-u-2-3 full-on-mobile"><p><input type="text" id="update-poi-name" readonly></p></div>
                            </div>
                            <h2><?php echo I18N::resolve("poi.update.objective"); ?></h2>
                            <div class="pure-g">
                                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-poi-objective">
                                    <?php
                                        // Sorts objectives and rewards alphabetically according to their translated strings.
                                        /*function sortByI18N($a, $b) {
                                            return strcmp($a["i18n"], $b["i18n"]);
                                        }*/

                                        /*
                                            We'll sort the research objectives by their first respective categories.
                                            Put all the research objectives into an array ($cats) of the structure
                                            $cats[CATEGORY][RESEARCH OBJECTIVE][PARAMETERS ETC.]
                                        */
                                        $cats = array();
                                        foreach (Research::OBJECTIVES as $objective => $data) {
                                            // Skip unknown since it shouldn't be displayed
                                            if ($objective === "unknown") continue;
                                            $cats[$data["categories"][0]][$objective] = $data;
                                        }

                                        /*
                                            After the objectives have been sorted into proper categories, we'll resolve
                                            the I18N string for each research objective, one category at a time.
                                        */
                                        foreach ($cats as $category => $categorizedObjectives) {
                                            foreach ($categorizedObjectives as $objective => $data) {
                                                // Use the plural string of the objective by default
                                                $i18n = I18N::resolve("objective.{$objective}.plural");
                                                // Replace parameters (e.g. {%1}) with placeholders
                                                for ($i = 0; $i < count($data["params"]); $i++) {
                                                    $i18n = str_replace("{%".($i+1)."}", I18N::resolve("parameter.".$data["params"][$i].".placeholder"), $i18n);
                                                }
                                                // Now save the final localized string back into the objective
                                                $categorizedObjectives[$objective]["i18n"] = $i18n;
                                            }

                                            //uasort($categorizedObjectives, "sortByI18N");

                                            /*
                                                Create a group for each category of objectives, then output each of the
                                                objectives within that category to the selection box.
                                            */
                                            echo '<optgroup label="'.I18N::resolve("category.objective.{$category}").'">';
                                            foreach ($categorizedObjectives as $objective => $data) {
                                                $text = I18N::resolve("objective.{$objective}.plural");
                                                echo '<option value="'.$objective.'">'.$data["i18n"].'</option>';
                                            }
                                            echo '</optgroup>';
                                        }

                                        /*
                                        $objectives = Research::OBJECTIVES;
                                        foreach ($objectives as $objective => $data) {
                                            if ($objective === "unknown") continue;
                                            $objectives[$objective]["i18n"] = I18N::resolve("objective.{$objective}.plural");
                                        }
                                        uasort($objectives, "sortByI18N");

                                        foreach ($objectives as $objective => $data) {
                                            if ($objective === "unknown") continue;
                                            $text = I18N::resolve("objective.{$objective}.plural");
                                            echo '<option value="'.$objective.'">'.$text.'</option>';
                                        }
                                        */
                                    ?>
                                </select></p></div>
                            </div>
                            <div class="research-params objective-params">
                                <?php
                                    foreach (Research::PARAMETERS as $param => $class) {
                                        $inst = new $class();
                                        if (in_array("objectives", $inst->getAvailable())) {
                                            ?>
                                                <div id="update-poi-objective-param-<?php echo $param; ?>-box" class="pure-g research-parameter objective-parameter">
                                                    <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("parameter.{$param}.label"); ?>:</p></div>
                                                    <div class="pure-u-2-3 full-on-mobile"><p><?php echo $inst->html("update-poi-objective-param-{$param}-input", "parameter"); ?></p></div>
                                                </div>
                                            <?php
                                        }
                                    }
                                ?>
                                <script>
                                    function getObjectiveParameter(param) {
                                        switch (param) {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("objectives", $inst->getAvailable())) {
                                                        echo "case '{$param}':\n";
                                                        echo $inst->writeJS("update-poi-objective-param-{$param}-input")."\n";
                                                    }
                                                }
                                            ?>
                                        }
                                    }
                                    function parseObjectiveParameter(param, data) {
                                        switch (param) {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("objectives", $inst->getAvailable())) {
                                                        echo "case '{$param}':\n";
                                                        echo $inst->parseJS("update-poi-objective-param-{$param}-input")."\n";
                                                        echo "break;\n";
                                                    }
                                                }
                                            ?>
                                        }
                                    }
                                    $("#update-poi-objective").on("change", function() {
                                        <?php
                                            foreach (Research::PARAMETERS as $param => $class) {
                                                $inst = new $class();
                                                if (in_array("objectives", $inst->getAvailable())) {
                                                    echo "$('#update-poi-objective-param-{$param}-box').hide();";
                                                }
                                            }
                                        ?>
                                        var show = objectives[$("#update-poi-objective").val()].params;
                                        for (var i = 0; i < show.length; i++) {
                                            $("#update-poi-objective-param-" + show[i] + "-box").show();
                                        }
                                    });
                                </script>
                            </div>
                            <h2><?php echo I18N::resolve("poi.update.reward"); ?></h2>
                            <div class="pure-g">
                                <div class="pure-u-5-5 full-on-mobile"><p><select id="update-poi-reward">
                                    <?php
                                        /*
                                            We'll sort the research rewards by their first respective categories.
                                            Put all the research rewards into an array ($cats) of the structure
                                            $cats[CATEGORY][RESEARCH REWARD][PARAMETERS ETC.]
                                        */
                                        $cats = array();
                                        foreach (Research::REWARDS as $reward => $data) {
                                            // Skip unknown since it shouldn't be displayed
                                            if ($reward === "unknown") continue;
                                            $cats[$data["categories"][0]][$reward] = $data;
                                        }

                                        /*
                                            After the rewards have been sorted into proper categories, we'll resolve
                                            the I18N string for each research reward, one category at a time.
                                        */
                                        foreach ($cats as $category => $categorizedRewards) {
                                            foreach ($categorizedRewards as $reward => $data) {
                                                // Use the plural string of the reward by default
                                                $i18n = I18N::resolve("reward.{$reward}.plural");
                                                // Replace parameters (e.g. {%1}) with placeholders
                                                for ($i = 0; $i < count($data["params"]); $i++) {
                                                    $i18n = str_replace("{%".($i+1)."}", I18N::resolve("parameter.".$data["params"][$i].".placeholder"), $i18n);
                                                }
                                                // Now save the final localized string back into the reward
                                                $categorizedRewards[$reward]["i18n"] = $i18n;
                                            }

                                            //uasort($categorizedRewards, "sortByI18N");

                                            /*
                                                Create a group for each category of rewards, then output each of the
                                                rewards within that category to the selection box.
                                            */
                                            echo '<optgroup label="'.I18N::resolve("category.reward.{$category}").'">';
                                            foreach ($categorizedRewards as $reward => $data) {
                                                $text = I18N::resolve("reward.{$reward}.plural");
                                                echo '<option value="'.$reward.'">'.$data["i18n"].'</option>';
                                            }
                                            echo '</optgroup>';
                                        }

                                        /*
                                        $rewards = Research::REWARDS;
                                        foreach ($rewards as $reward => $data) {
                                            if ($reward === "unknown") continue;
                                            $rewards[$reward]["i18n"] = I18N::resolve("reward.{$reward}.plural");
                                        }
                                        uasort($rewards, "sortByI18N");

                                        foreach ($rewards as $reward => $data) {
                                            if ($reward === "unknown") continue;
                                            $text = I18N::resolve("reward.{$reward}.plural");
                                            echo '<option value="'.$reward.'">'.$text.'</option>';
                                        }
                                        */
                                    ?>
                                </select></p></div>
                            </div>
                            <div class="research-params reward-params">
                                <?php
                                    foreach (Research::PARAMETERS as $param => $class) {
                                        $inst = new $class();
                                        if (in_array("rewards", $inst->getAvailable())) {
                                            ?>
                                                <div id="update-poi-reward-param-<?php echo $param; ?>-box" class="pure-g research-parameter reward-parameter">
                                                    <div class="pure-u-1-3 full-on-mobile"><p><?php echo I18N::resolve("parameter.{$param}.label"); ?>:</p></div>
                                                    <div class="pure-u-2-3 full-on-mobile"><p><?php echo $inst->html("update-poi-reward-param-{$param}-input", "parameter"); ?></p></div>
                                                </div>
                                            <?php
                                        }
                                    }
                                ?>
                                <script>
                                    function getRewardParameter(param) {
                                        switch (param) {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("rewards", $inst->getAvailable())) {
                                                        echo "case '{$param}':\n";
                                                        echo $inst->writeJS("update-poi-reward-param-{$param}-input")."\n";
                                                    }
                                                }
                                            ?>
                                        }
                                    }
                                    function parseRewardParameter(param, data) {
                                        switch (param) {
                                            <?php
                                                foreach (Research::PARAMETERS as $param => $class) {
                                                    $inst = new $class();
                                                    if (in_array("rewards", $inst->getAvailable())) {
                                                        echo "case '{$param}':\n";
                                                        echo $inst->parseJS("update-poi-reward-param-{$param}-input")."\n";
                                                        echo "break;\n";
                                                    }
                                                }
                                            ?>
                                        }
                                    }
                                    $("#update-poi-reward").on("change", function() {
                                        <?php
                                            foreach (Research::PARAMETERS as $param => $class) {
                                                $inst = new $class();
                                                if (in_array("rewards", $inst->getAvailable())) {
                                                    echo "$('#update-poi-reward-param-{$param}-box').hide();";
                                                }
                                            }
                                        ?>
                                        var show = rewards[$("#update-poi-reward").val()].params;
                                        for (var i = 0; i < show.length; i++) {
                                            $("#update-poi-reward-param-" + show[i] + "-box").show();
                                        }
                                    });
                                </script>
                            </div>
                            <div class="cover-button-spacer"></div>
                            <div class="pure-g">
                                <div class="pure-u-1-2 right-align"><span type="button" id="update-poi-cancel" class="button-standard split-button button-spaced left"><?php echo I18N::resolve("ui.button.cancel"); ?></span></div>
                                <div class="pure-u-1-2"><span type="button" id="update-poi-submit" class="button-submit split-button button-spaced right"><?php echo I18N::resolve("poi.update.submit"); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="add-poi-working" class="cover-box">
                    <div class="cover-box-inner tiny">
                        <div class="cover-box-content">
                            <div><i class="fas fa-spinner loading-spinner spinner-large"></i></div>
                            <p><?php echo I18N::resolve("poi.add.processing"); ?></p>
                        </div>
                    </div>
                </div>
                <div id="update-poi-working" class="cover-box">
                    <div class="cover-box-inner tiny">
                        <div class="cover-box-content">
                            <div><i class="fas fa-spinner loading-spinner spinner-large"></i></div>
                            <p><?php echo I18N::resolve("poi.update.processing"); ?></p>
                        </div>
                    </div>
                </div>
                <div id="map" class="full-container"></div>
                </div>
                <div id="settings-container" class="full-container hidden-by-default">
                    <div class="header">
                        <h1>Settings</h1>
                        <h2>Personalize your experience</h2>
                    </div>
                    <div class="content pure-form">
                        <h2 class="content-subhead">Map providers</h2>
                            <div class="pure-g">
                                <div class="pure-u-1-3 full-on-mobile">
                                    <p class="setting-name"><?php echo I18N::resolve("user_setting.directions_provider.name"); ?>:</p>
                                </div>
                                <div class="pure-u-2-3 full-on-mobile">
                                    <p><select class="user-setting" data-key="naviProvider">
                                        <option value=""><?php echo I18N::resolve("user_settings.value.default"); ?></option>
                                        <option value="bing"><?php echo I18N::resolve("setting.map.provider.directions.option.bing"); ?></option>
                                        <option value="google"><?php echo I18N::resolve("setting.map.provider.directions.option.google"); ?></option>
                                        <option value="here"><?php echo I18N::resolve("setting.map.provider.directions.option.here"); ?></option>
                                        <option value="mapquest"><?php echo I18N::resolve("setting.map.provider.directions.option.mapquest"); ?></option>
                                        <option value="waze"><?php echo I18N::resolve("setting.map.provider.directions.option.waze"); ?></option>
                                        <option value="yandex"><?php echo I18N::resolve("setting.map.provider.directions.option.yandex"); ?></option>
                                    </select></p>
                                </div>
                            </div>
                        <h2 class="content-subhead">Appearance</h2>
                        <?php
                            if (Config::get("themes/color/user-settings/allow-personalization")) {
                                ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolve("user_setting.interface_theme.name"); ?>:</p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p><select class="user-setting" data-key="theme">
                                                <option value=""><?php echo I18N::resolve("user_settings.value.default"); ?></option>
                                                <option value="light"><?php echo I18N::resolve("setting.themes.color.user_settings.theme.option.light"); ?></option>
                                                <option value="dark"><?php echo I18N::resolve("setting.themes.color.user_settings.theme.option.dark"); ?></option>
                                            </select></p>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>
                        <?php
                            if (Config::get("themes/color/map/allow-personalization")) {
                                ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolve("user_setting.map_theme.name"); ?>:</p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p><select class="user-setting" data-key="mapStyle/mapbox">
                                                <option value=""><?php echo I18N::resolve("user_settings.value.default"); ?></option>
                                                <option value="basic"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.basic"); ?></option>
                                                <option value="streets"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.streets"); ?></option>
                                                <option value="bright"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.bright"); ?></option>
                                                <option value="light"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.light"); ?></option>
                                                <option value="dark"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.dark"); ?></option>
                                                <option value="satellite"><?php echo I18N::resolve("setting.themes.color.map.theme.mapbox.option.satellite"); ?></option>
                                            </select></p>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>
                        <?php
                            if (Config::get("themes/icons/allow-personalization")) {
                                $opt = new IconPackOption(true);
                                ?>
                                    <div class="pure-g">
                                        <div class="pure-u-1-3 full-on-mobile">
                                            <p class="setting-name"><?php echo I18N::resolve("user_setting.icons.name"); ?>:</p>
                                        </div>
                                        <div class="pure-u-2-3 full-on-mobile">
                                            <p><?php echo $opt->getControl(null, null, "icon-selector", array("data-key" => "iconSet", "class" => "user-setting")); ?></p>
                                        </div>
                                    </div>
                                    <?php
                                        echo $opt->getFollowingBlock();
                                    ?>
                                <?php
                            }
                        ?>
                        <p class="buttons"><input type="button" id="user-settings-save" class="button-submit" value="<?php echo I18N::resolve("ui.button.save"); ?>"></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            var objectives = <?php echo json_encode(Research::OBJECTIVES); ?>;
            var rewards = <?php echo json_encode(Research::REWARDS); ?>;

            var defaults = {
                iconSet: "<?php echo Config::get("themes/icons/default"); ?>",
                mapProvider: "<?php echo $provider; ?>",
                naviProvider: "<?php echo Config::get("map/provider/directions"); ?>",
                mapStyle: {
                    mapbox: "<?php echo Config::get("themes/color/map/theme/{$provider}"); ?>"
                },
                theme: "<?php echo Config::get("themes/color/user-settings/theme"); ?>",
                center: {
                    latitude: <?php echo Config::get("map/default/center/latitude"); ?>,
                    longitude: <?php echo Config::get("map/default/center/longitude"); ?>
                },
                zoom: <?php echo Config::get("map/default/zoom"); ?>
            };

            var forceDefaults = [
                <?php
                    $forced = array();
                    if (!Config::get("themes/color/user-settings/allow-personalization")) $forced[] = '"theme"';
                    if (!Config::get("themes/color/map/allow-personalization")) $forced[] = '"mapStyle/mapbox"';
                    if (!Config::get("themes/icons/allow-personalization")) $forced[] = '"iconSet"';
                    echo implode(', ', $forced);
                ?>
            ];

            var settings = $.extend(true, {}, defaults);
            settings.theme = "";
            settings.mapStyle.mapbox = "";
            settings.get = function(key, ignoreDefault) {
                ignoreDefault = ignoreDefault || false;

                var tree = key.split("/");
                var value = settings;
                for (var i = 0; i < tree.length; i++) {
                    value = value[tree[i]];
                }

                if (forceDefaults.indexOf(key) >= 0 || (!ignoreDefault && value == "")) {
                    value = defaults;
                    for (var i = 0; i < tree.length; i++) {
                        value = value[tree[i]];
                    }
                }
                return value;
            };


            var permissions = {
                <?php
                    $clientside_perms = array("report-research", "overwrite-research", "submit-poi");
                    for ($i = 0; $i < count($clientside_perms); $i++) {
                        $clientside_perms[$i] = '"'.$clientside_perms[$i].'": '.(Auth::getCurrentUser()->hasPermission($clientside_perms[$i]) ? "true" : "false");
                    }
                    echo implode(", ", $clientside_perms);
                ?>
            }

            var iconSets = {
                <?php
                    $icons = Theme::listIcons();

                    $themes = Theme::listIconSets();
                    $themejs = array();
                    $restrictiveLoadThemes = array(
                        Config::get("themes/icons/default")
                    );
                    foreach ($themes as $theme) {
                        if (!Config::get("themes/icons/allow-personalization") && in_array($theme, $restrictiveLoadThemes)) return;

                        $iconSet = Theme::getIconSet($theme);
                        $iconKv = array();
                        foreach ($icons as $icon) {
                            $iconKv[] = "'{$icon}': '".$iconSet->getIconUrl($icon)."'";
                        }
                        $themejs[] = "'{$theme}': {".implode(", ", $iconKv)."}";
                    }

                    echo implode(", ", $themejs);
                ?>
            };
        </script>
        <script src="./js/ui.js"></script>
        <script src="./js/main.js?t="<?php echo time(); ?>></script>
        <script>
            if (hasLocalStorageSupport()) {
                var storedSettings = JSON.parse(localStorage.getItem("settings"));
                if (storedSettings !== null) {
                    var keys = Object.keys(storedSettings);
                    for (var i = 0; i < keys.length; i++) {
                        settings[keys[i]] = storedSettings[keys[i]];
                    }
                }
            }

            $("head").append('<link rel="stylesheet" type="text/css" href="./css/' + settings.get("theme") + '.css?v=<?php echo time(); ?>">');

            mapboxgl.accessToken = '<?php echo Config::get("map/provider/mapbox/access-token"); ?>';
            var map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/' + (settings.get("mapStyle/mapbox")) + '-v9',
                center: [settings.center.longitude, settings.center.latitude],
                zoom: settings.zoom
            });
            map.addControl(new mapboxgl.NavigationControl());
            map.addControl(new mapboxgl.GeolocateControl({
                positionOptions: {
                    enableHighAccuracy: false,
                    timeout: 5000
                },
                trackUserLocation: true
            }));

            var lastCenter = map.getCenter();
            var lastZoom = map.getZoom();
            setInterval(function() {
                var center = map.getCenter();
                var zoom = map.getZoom();
                if (center != lastCenter || zoom != lastZoom) {
                    lastCenter = center;
                    lastZoom = zoom;
                    settings.center.longitude = center.lng;
                    settings.center.latitude = center.lat;
                    settings.zoom = zoom;
                    saveSettings();
                }
            }, 1000);
        </script>
    </body>
</html>
