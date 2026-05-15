<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
                            <ul class="os-tabs schedule-tabs" role="tablist">
                                <li role="presentation" class="os-tabs__item os-tabs__item--active"><a href="#programming"
                                                                          aria-controls="programming"
                                                                          role="tab" data-os-tab="programming"
                                                                          data-os-pane="programming"
                                                                          onclick="setFilterEvents(true);"><span
                                                class="os-hide-mobile"><?php echo esc_html($programming_tab_label); ?></span><span
                                                class="os-show-mobile"><?php echo esc_html($programming_mobile_label); ?></span></a>
                                </li>
                                <li role="presentation" class="os-tabs__item"><a href="#essentials" aria-controls="programming" role="tab"
                                                           data-os-tab="essentials"
                                                           data-os-pane="programming"
                                                           onclick="setFilterEvents(false);"><?php echo esc_html($essentials_tab_name); ?></a>
                                </li>
                                <?php if ($theming != "schedule") { ?>
                                    <li role="presentation" class="os-tabs__item"><a href="#hours" aria-controls="hours" role="tab"
                                                                data-os-tab="hours"
                                                                id="hours-tab" onclick="scrollTopMenu()"><?php echo esc_html($hours_tab_label); ?></a></li>
                                <?php } else { ?>
                                    <li role="presentation" class="os-tabs__item"><a href="#map" aria-controls="map" role="tab"
                                                                data-os-tab="map"
                                                                id="map-tab" onclick="scrollTopMenu()"><?php echo esc_html($map_tab_label); ?></a></li>
                                    <?php
                                } ?>
                            </ul>
