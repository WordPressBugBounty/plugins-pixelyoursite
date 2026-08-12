<?php
/**
 * PYS MCP activity-log subpage — rendered inside html-wrapper-single-page.php
 * via the `pys_admin_pixelyoursite_mcp_log` action. `$viewModel` is provided
 * by `\PixelYourSite\MCP\AdminPage::renderLogPageContent()`. Filters and
 * pagination are plain GET params (bookmarkable; no AJAX except Clear log).
 *
 * @var array $viewModel
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $viewModel ) || ! is_array( $viewModel ) ) {
	return;
}

$entries       = is_array( $viewModel['entries'] ?? null ) ? $viewModel['entries'] : array();
$tools         = is_array( $viewModel['tools'] ?? null ) ? $viewModel['tools'] : array();
$filters       = is_array( $viewModel['filters'] ?? null ) ? $viewModel['filters'] : array();
$paged         = (int) ( $viewModel['paged'] ?? 1 );
$totalPages    = (int) ( $viewModel['total_pages'] ?? 1 );
$totalFiltered = (int) ( $viewModel['total_filtered'] ?? 0 );
$totalAll      = (int) ( $viewModel['total_all'] ?? 0 );
$perPage       = (int) ( $viewModel['per_page'] ?? 25 );
$baseUrl       = (string) ( $viewModel['base_url'] ?? '' );
$settingsUrl   = (string) ( $viewModel['settings_url'] ?? '' );
$logUrl        = (string) ( $viewModel['log_url'] ?? '' );

$filterTool   = (string) ( $filters['tool'] ?? '' );
$filterStatus = (string) ( $filters['status'] ?? '' );
$filterPeriod = (string) ( $filters['period'] ?? '' );
$filterSearch = (string) ( $filters['search'] ?? '' );
$hasFilters   = '' !== $filterTool || '' !== $filterStatus || '' !== $filterPeriod || '' !== $filterSearch;

$rangeFrom = 0 === $totalFiltered ? 0 : ( $paged - 1 ) * $perPage + 1;
$rangeTo   = min( $paged * $perPage, $totalFiltered );

$pageUrl = static function ( $page ) use ( $baseUrl ) {
	return esc_url( add_query_arg( 'paged', max( 1, (int) $page ), $baseUrl ) );
};

$statusIcon = static function ( $ok ) {
	if ( $ok ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 12 10 17 19 7"></polyline></svg>';
	}
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="6" y1="18" x2="18" y2="6"></line></svg>';
};
?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 setting-wrapper pys-mcp-admin">
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e( 'MCP activity log', 'pys' ); ?></h4>
            <div class="d-flex align-items-center" style="gap: 8px;">
                <a class="btn-small btn-gray secondary_heading" href="<?php echo esc_url( $settingsUrl ); ?>">&larr; <?php _e( 'MCP settings', 'pys' ); ?></a>
                <button type="button" class="btn-small btn-gray btn-small-icon secondary_heading loadable" id="pys-mcp-clear-log-btn"><i class="icon-delete"></i><?php _e( 'Clear log', 'pys' ); ?></button>
            </div>
        </div>
        <div class="card-body">

            <!-- ================================================== FILTERS -->
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="mb-24">
                <input type="hidden" name="page" value="<?php echo esc_attr( \PixelYourSite\MCP\AdminPage::LOG_PAGE_SLUG ); ?>"/>
                <div class="d-flex align-items-center" style="gap: 12px; flex-wrap: wrap;">
                    <select name="pys_tool" class="pys_status input-standard" style="width: auto; min-width: 180px;">
                        <option value=""><?php _e( 'All tools', 'pys' ); ?></option>
						<?php foreach ( $tools as $toolOption ) : ?>
                            <option value="<?php echo esc_attr( $toolOption ); ?>" <?php selected( $filterTool, $toolOption ); ?>><?php echo esc_html( $toolOption ); ?></option>
						<?php endforeach; ?>
                    </select>

                    <select name="pys_status" class="pys_status input-standard" style="width: auto; min-width: 150px;">
                        <option value=""><?php _e( 'Any status', 'pys' ); ?></option>
                        <option value="success" <?php selected( $filterStatus, 'success' ); ?>><?php _e( 'Success', 'pys' ); ?></option>
                        <option value="error" <?php selected( $filterStatus, 'error' ); ?>><?php _e( 'Error', 'pys' ); ?></option>
                    </select>

                    <select name="pys_period" class="pys_status input-standard" style="width: auto; min-width: 150px;">
                        <option value=""><?php _e( 'All time', 'pys' ); ?></option>
                        <option value="24h" <?php selected( $filterPeriod, '24h' ); ?>><?php _e( 'Last 24 hours', 'pys' ); ?></option>
                        <option value="7d" <?php selected( $filterPeriod, '7d' ); ?>><?php _e( 'Last 7 days', 'pys' ); ?></option>
                        <option value="30d" <?php selected( $filterPeriod, '30d' ); ?>><?php _e( 'Last 30 days', 'pys' ); ?></option>
                    </select>

                    <input type="text" name="pys_search" class="input-standard" style="width: auto; min-width: 220px;"
                           placeholder="<?php esc_attr_e( 'Search note / tool / IP…', 'pys' ); ?>"
                           value="<?php echo esc_attr( $filterSearch ); ?>"/>

                    <button type="submit" class="btn btn-primary btn-primary-type2"><?php _e( 'Filter', 'pys' ); ?></button>

					<?php if ( $hasFilters ) : ?>
                        <a class="btn-small btn-gray secondary_heading" href="<?php echo esc_url( $logUrl ); ?>"><?php _e( 'Reset', 'pys' ); ?></a>
					<?php endif; ?>
                </div>
            </form>

            <!-- ================================================== SUMMARY -->
            <p class="text-gray mb-16">
				<?php
				if ( 0 === $totalFiltered ) {
					$hasFilters
						? _e( 'No entries match the current filters.', 'pys' )
						: _e( 'No write-tool calls recorded yet.', 'pys' );
				} else {
					printf(
						/* translators: 1: range start, 2: range end, 3: filtered total, 4: overall total */
						esc_html__( 'Showing %1$d–%2$d of %3$d entries (%4$d total in the log).', 'pys' ),
						(int) $rangeFrom,
						(int) $rangeTo,
						(int) $totalFiltered,
						(int) $totalAll
					);
				}
				?>
            </p>

            <!-- ==================================================== TABLE -->
			<?php if ( ! empty( $entries ) ) : ?>
                <table class="pys-mcp-activity">
                    <thead>
                    <tr>
                        <th class="pys-mcp-activity__status-col"></th>
                        <th><?php _e( 'When', 'pys' ); ?></th>
                        <th><?php _e( 'Tool', 'pys' ); ?></th>
                        <th><?php _e( 'Token', 'pys' ); ?></th>
                        <th><?php _e( 'Note', 'pys' ); ?></th>
                        <th><?php _e( 'IP', 'pys' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
					<?php
					foreach ( $entries as $entry ) :
						$status   = (string) ( $entry['result_status'] ?? '' );
						$statusOk = 'success' === $status;
						?>
                        <tr>
                            <td class="pys-mcp-activity__status-cell">
                                <span class="pys-mcp-status pys-mcp-status--<?php echo $statusOk ? 'ok' : 'fail'; ?>"
                                      title="<?php echo esc_attr( $status ); ?>"
                                      aria-label="<?php echo esc_attr( $status ); ?>">
                                    <?php echo $statusIcon( $statusOk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG ?>
                                </span>
                            </td>
                            <td class="pys-mcp-activity__when">
								<?php echo esc_html( isset( $entry['ts'] ) ? date_i18n( 'Y-m-d H:i:s', (int) $entry['ts'] ) : '—' ); ?>
                            </td>
                            <td><code><?php echo esc_html( (string) ( $entry['tool'] ?? '' ) ); ?></code></td>
                            <td><?php echo esc_html( (string) ( $entry['token'] ?? '' ) ); ?></td>
                            <td class="pys-mcp-activity__note"><?php echo esc_html( (string) ( $entry['mcp_note'] ?? '' ) ); ?></td>
                            <td class="pys-mcp-activity__ip"><?php echo esc_html( (string) ( $entry['actor_ip'] ?? '' ) ); ?></td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>
			<?php endif; ?>

            <!-- =============================================== PAGINATION -->
			<?php if ( $totalPages > 1 ) : ?>
                <div class="d-flex align-items-center justify-content-between mt-24" style="gap: 12px;">
                    <div class="d-flex align-items-center" style="gap: 8px;">
						<?php if ( $paged > 1 ) : ?>
                            <a class="btn-small btn-gray secondary_heading" href="<?php echo $pageUrl( 1 ); ?>">&laquo;</a>
                            <a class="btn-small btn-gray secondary_heading" href="<?php echo $pageUrl( $paged - 1 ); ?>">&lsaquo; <?php _e( 'Prev', 'pys' ); ?></a>
						<?php endif; ?>
                    </div>

                    <span class="text-gray">
                        <?php
                        printf(
	                        /* translators: 1: current page, 2: total pages */
	                        esc_html__( 'Page %1$d of %2$d', 'pys' ),
	                        (int) $paged,
	                        (int) $totalPages
                        );
                        ?>
                    </span>

                    <div class="d-flex align-items-center" style="gap: 8px;">
						<?php if ( $paged < $totalPages ) : ?>
                            <a class="btn-small btn-gray secondary_heading" href="<?php echo $pageUrl( $paged + 1 ); ?>"><?php _e( 'Next', 'pys' ); ?> &rsaquo;</a>
                            <a class="btn-small btn-gray secondary_heading" href="<?php echo $pageUrl( $totalPages ); ?>">&raquo;</a>
						<?php endif; ?>
                    </div>
                </div>
			<?php endif; ?>

            <div class="pys-mcp-flash alert mt-24" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
(function ($) {
	'use strict';

	let PYS_MCP_LOG = {
		ajaxUrl:  <?php echo wp_json_encode( $viewModel['ajax_url'] ); ?>,
		nonce:    <?php echo wp_json_encode( $viewModel['nonce'] ); ?>,
		clearLog: <?php echo wp_json_encode( $viewModel['ajax_clear_log'] ); ?>,
		logUrl:   <?php echo wp_json_encode( $logUrl ); ?>
	};

	function flash(type, message) {
		let $flash = $('.pys-mcp-flash').first();
		$flash.removeClass('alert-success alert-danger alert-warning').addClass('alert-' + type)
			.text(message).show();
	}

	function pysConfirm(opts, onConfirm) {
		if (typeof $.confirm === 'function') {
			$.confirm({
				title: opts.title,
				content: '<p>' + opts.content + '</p>',
				type: 'pys',
				typeAnimated: true,
				autoClose: 'cancelAction|10000',
				buttons: {
					yesAction: {
						text: opts.yesText || 'Yes',
						btnClass: 'btn-pys btn-pys-red',
						action: onConfirm
					},
					cancelAction: { text: 'Cancel' }
				}
			});
			return;
		}
		if (window.confirm(opts.title + '\n\n' + opts.content)) {
			onConfirm();
		}
	}

	$(function () {
		$(document).on('click', '#pys-mcp-clear-log-btn', function (e) {
			e.preventDefault();
			let $btn = $(e.currentTarget);
			pysConfirm({
				title:   'Clear the entire MCP activity log?',
				content: 'All recorded write-tool calls will be deleted. This cannot be undone.',
				yesText: 'Yes, clear log'
			}, function () {
				$btn.prop('disabled', true).addClass('is-loading');
				$.post(PYS_MCP_LOG.ajaxUrl, {
					action: PYS_MCP_LOG.clearLog,
					nonce:  PYS_MCP_LOG.nonce
				}).done(function (res) {
					if (!res || !res.success) {
						flash('danger', 'Failed to clear log.');
						return;
					}
					// Reload without filters — the log is empty now anyway.
					window.location.href = PYS_MCP_LOG.logUrl;
				}).fail(function () {
					flash('danger', 'Network error during clear log.');
				}).always(function () {
					$btn.prop('disabled', false).removeClass('is-loading');
				});
			});
		});
	});
})(jQuery);
</script>