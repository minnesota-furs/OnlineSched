<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
                                    <div class="schedule-sort os-well">
                                        <div class="os-row">
                                            <div class="os-col-sm-3">
                                                <div class="schedule-search">
                                                    <input class="os-form-control" type="text"
                                                           placeholder="Type to search..."
                                                           id="schedule-search-text" value=""
                                                           autocomplete='off' <?php if ($theming == 'schedule') {
                                                        echo " style='display:none;'";
                                                    } ?>>
                                                </div>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-tags">
                                                    <option selected value="all">All Tags</option>
                                                </select>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-days">
                                                    <option value="all">All Days</option>
                                                    <option selected value="Current">Now and Future</option>
                                                </select>
                                            </div>
                                            <div class="os-col-sm-2">
                                                <select class="os-form-control" id="schedule-select-rooms">
                                                    <option selected value="all">All Rooms</option>
                                                </select>
                                            </div>
                                            <?php if (!$liveStreaming && $theming != "schedule") { ?>
                                                <div class="os-col-sm-1 schedule-favorites-filter"
                                                     style="display: flex; align-items: center;">
                                                    <button class="os-btn os-btn--default os-btn--sm os-btn--block schedule-favorites-toggle"
                                                            id="schedule-favorites-toggle" title="Show Favorites Only"
                                                            aria-pressed="false"
                                                            style="display: flex; align-items: center; justify-content: center; height: 34px;">
                                                        <span class="favorite-label-mobile"
                                                              style="margin-right: 4px; display: none;">Favorite</span>
                                                        <i class="<?php echo esc_attr(onlinesched_get_favorite_icon_classes(false)); ?>" aria-hidden="true"
                                                           style="color: var(--os-gold, #f6c700);"></i>
                                                        <span class="os-sr-only">Show Favorites Only</span>
                                                    </button>
                                                </div>
                                            <?php } ?>
                                            <div class="os-col-sm-2 schedule-reset">
                                                <button class="os-btn os-btn--primary os-btn--sm os-btn--block" disabled
                                                        id="schedule-reset"><i
                                                            class="fa fa-refresh" aria-hidden="true"></i> Reset
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php do_action('os_after_schedule_filters'); ?>
