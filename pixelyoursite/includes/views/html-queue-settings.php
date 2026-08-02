<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 setting-wrapper">
    <!-- Queue System Status -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue System Status', 'pys'); ?></h4>
            <?php renderProBadge(); ?>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="gap-24">
                    <div>
                        <div class="d-flex align-items-center">
                            <?php renderDummySwitcher(); ?>
                            <h4 class="switcher-label secondary_heading"><?php _e('Enable Queue System', 'pys'); ?></h4>
                        </div>
                        <p class="text-gray mt-4">
                            <?php _e('Enable the queue system to replace async tasks. This will improve performance by processing events in batches instead of individually.', 'pys'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Configuration -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Processing Configuration', 'pys'); ?></h4>
            <?php renderProBadge(); ?>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="gap-24">
                    <div class="d-flex align-items-center number-option-block">
                        <label class="primary_heading"><?php _e('Max Events Per Batch:', 'pys'); ?></label>
                        <?php renderDummyNumberInput(10); ?>
                    </div>
                    <p class="text-gray">
                        <?php _e('Maximum number of events to process in one batch (1-1000). Higher values may improve performance but use more memory.', 'pys'); ?>
                    </p>

                    <div>
                        <h4 class="primary_heading mb-4"><?php _e('Processing Interval:', 'pys'); ?></h4>
                        <?php renderDummySelectInput('5 minutes'); ?>

                        <p class="text-gray mt-4">
                            <?php _e('How often to process the events queue. More frequent processing provides faster event delivery but uses more server resources.', 'pys'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Limits -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue Limits', 'pys'); ?></h4>
            <?php renderProBadge(); ?>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="gap-24">
                    <div class="d-flex align-items-center number-option-block">
                        <label class="primary_heading"><?php _e('Max Queue Size:', 'pys'); ?></label>
                        <?php renderDummyNumberInput(100); ?>
                    </div>
                    <p class="text-gray">
                        <?php _e('Maximum number of events in queue before dropping new events (100-100000). Prevents memory issues during high traffic periods.', 'pys'); ?>
                    </p>

                    <div class="d-flex align-items-center number-option-block">
                        <label class="primary_heading"><?php _e('Max Retries:', 'pys'); ?></label>
                        <?php renderDummyNumberInput(3); ?>
                    </div>
                    <p class="text-gray">
                        <?php _e('Maximum number of retry attempts for failed events (0-10). Failed events will be retried this many times before being marked as permanently failed.', 'pys'); ?>
                    </p>

                    <div class="d-flex align-items-center number-option-block">
                        <label class="primary_heading"><?php _e('Retention Days:', 'pys'); ?></label>
                        <?php renderDummyNumberInput(1); ?>
                    </div>
                    <p class="text-gray">
                        <?php _e('Number of days to keep processed/failed events in the database (1-30). Older events will be automatically cleaned up to save space.', 'pys'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue Statistics', 'pys'); ?></h4>

            <div class="queue-actions">
                <?php renderProBadge(); ?>
                <button type="button" id="refresh-stats" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-refresh"></i> <?php _e('Refresh', 'pys'); ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="pro-feature-container">
                <div class="gap-24">
                    <div class="queue-stats-grid" id="queue-stats-container">
                        <div class="stat-card stat-pending">
                            <h3 class="stat-number" id="pending-count">0</h3>
                            <p class="stat-label"><?php _e('Pending Events', 'pys'); ?></p>
                        </div>
                        <div class="stat-card stat-processed">
                            <h3 class="stat-number" id="processed-count">0</h3>
                            <p class="stat-label"><?php _e('Processed Today', 'pys'); ?></p>
                        </div>
                        <div class="stat-card stat-failed">
                            <h3 class="stat-number" id="failed-count">0</h3>
                            <p class="stat-label"><?php _e('Failed Events', 'pys'); ?></p>
                        </div>
                        <div class="stat-card stat-total">
                            <h3 class="stat-number" id="total-count">0</h3>
                            <p class="stat-label"><?php _e('Total Events', 'pys'); ?></p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 flex-wrap queue-status-info">
                        <div class="col-md-6">
                            <p class="text-gray mb-12">
                                <strong><?php _e('Last Processed:', 'pys'); ?></strong>
                                <span id="last-processed"><?php _e('Never', 'pys'); ?></span>
                            </p>
                            <p class="text-gray mb-2">
                                <strong><?php _e('Queue Status:', 'pys'); ?></strong>
                                <span id="queue-status" class="queue-status idle">
                                    <?php _e('Idle', 'pys'); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-gray mb-12">
                                <strong><?php _e('Processing Rate:', 'pys'); ?></strong>
                                <span id="processing-rate">0</span> <?php _e('events/min', 'pys'); ?>
                            </p>
                            <p class="text-gray mb-2">
                                <strong><?php _e('Success Rate:', 'pys'); ?></strong>
                                <span id="success-rate">100%</span>
                            </p>
                        </div>
                    </div>

                    <div class="queue-management-actions">
                        <div class="d-flex gap-3 flex-wrap">
                            <button type="button" id="manual-process-queue" class="btn btn-primary">
                                <i class="fa fa-play"></i> <?php _e('Process Queue Now', 'pys'); ?>
                            </button>
                            <button type="button" id="clear-old-records" class="btn btn-warning">
                                <i class="fa fa-trash"></i> <?php _e('Clear Old Records', 'pys'); ?>
                            </button>
                            <button type="button" id="reset-failed-events" class="btn btn-secondary">
                                <i class="fa fa-refresh"></i> <?php _e('Reset Failed Events', 'pys'); ?>
                            </button>
                        </div>
                        <p class="text-gray mt-3">
                            <?php _e('Use these actions to manually manage your event queue. Process Queue Now will immediately start processing pending events. Clear Old Records will remove processed events older than the retention period.', 'pys'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
