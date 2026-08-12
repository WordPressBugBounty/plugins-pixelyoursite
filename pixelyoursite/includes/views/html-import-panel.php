<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Import panel body. Rendered inside html-import-export.php.
 *
 * @var array $import_ctx Provided by SiteProfileAdmin::render().
 */

$stage    = $import_ctx[ 'stage' ] ?? 'upload';
$messages = $import_ctx[ 'messages' ] ?? array();
$view     = $import_ctx[ 'view' ] ?? null;
$post_url = admin_url( 'admin-post.php' );

/**
 * Compact, truncated value renderer for the diff table.
 *
 * @param mixed $value
 * @return string
 */
$render_val = function( $value, $full = false ): string {
	if ( is_array( $value ) || is_object( $value ) ) {
		$out = wp_json_encode( $value );
	} elseif ( is_bool( $value ) ) {
		$out = $value ? 'true' : 'false';
	} else {
		$out = (string) $value;
	}

	if ( ! $full && mb_strlen( $out ) > 120 ) {
		$out = mb_substr( $out, 0, 117 ) . '…';
	}

	return $out === '' ? '(empty)' : $out;
};

/**
 * Format an ISO 8601 timestamp into the site's local date & time.
 *
 * @param string $iso
 * @return string
 */
$format_dt = function( $iso ): string {
	if ( empty( $iso ) ) {
		return '';
	}
	$ts = strtotime( $iso );
	if ( !$ts ) {
		return $iso;
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
};

/**
 * Render one incoming pixel row (the main pixel).
 *
 * @param string $slug
 * @param array  $pixel   Incoming pixel data.
 * @param array  $targets Override targets on this site.
 * @return void
 */
$render_import_pixel = function( $slug, $pixel, $targets ): void {
	$ref    = $pixel[ 'ref' ];
	$name   = 'pys_import[pixels][' . $slug . '][' . $ref . ']';
	$dom_id = 'imp_pixels_' . $slug . '_' . $ref;
	?>
    <div class="pys-profile-pixel">
        <div class="pys-profile-pixel__id">
            <span class="pys-profile-pixel__source"><?php echo esc_html( $pixel[ 'source_label' ] ); ?></span>
            ID <code><?php echo esc_html( $pixel[ 'id_masked' ] ); ?></code>
			<?php if ( empty( $pixel[ 'enabled' ] ) ) : ?>
                <span class="pys-profile-pixel__meta">disabled</span>
			<?php endif; ?>
        </div>

        <div class="pys-profile-pixel__actions pys-import-pixel-row">
            <div class="select-standard-wrap pys-profile-select">
                <select name="<?php echo esc_attr( $name ); ?>[action]" class="select-standard pys-import-action">
                    <option value="skip">Skip</option>
                    <option value="override">Override existing</option>
                </select>
            </div>

			<?php if ( !empty( $targets ) ) : ?>
                <div class="select-standard-wrap pys-profile-select pys-import-target">
                    <select name="<?php echo esc_attr( $name ); ?>[target]" class="select-standard">
						<?php foreach ( $targets as $t ) : ?>
                            <option value="<?php echo esc_attr( $t[ 'ref' ] ); ?>"><?php
								echo esc_html( $t[ 'label' ] . ' — ID ' . $t[ 'id_masked' ] );
							?></option>
						<?php endforeach; ?>
                    </select>
                </div>
			<?php endif; ?>

			<?php if ( !empty( $pixel[ 'has_token' ] ) ) : ?>
                <div class="small-checkbox pys-import-token">
                    <input type="checkbox"
                           id="<?php echo esc_attr( $dom_id ); ?>"
                           name="<?php echo esc_attr( $name ); ?>[token]" value="1" class="small-control-input">
                    <label class="small-control small-checkbox-label"
                           for="<?php echo esc_attr( $dom_id ); ?>">
                        <span class="small-control-indicator"><i class="icon-check"></i></span>
                        <span class="small-control-description">Include token</span>
                    </label>
                </div>
			<?php endif; ?>
        </div>
    </div>
	<?php
};
?>

<!-- Messages (reuse the plugin's standard .alert styling) -->
<?php foreach ( ( $messages[ 'errors' ] ?? array() ) as $m ) : ?>
    <div class="alert alert-danger"><?php echo esc_html( $m ); ?></div>
<?php endforeach; ?>
<?php foreach ( ( $messages[ 'warnings' ] ?? array() ) as $m ) : ?>
    <div class="alert alert-warning"><?php echo esc_html( $m ); ?></div>
<?php endforeach; ?>
<?php foreach ( ( $messages[ 'success' ] ?? array() ) as $m ) : ?>
    <div class="alert alert-success"><?php echo esc_html( $m ); ?></div>
<?php endforeach; ?>

<div class="gap-24 mt-24">


	<?php if ( $stage === 'upload' ) : ?>

        <!-- ===================== STAGE: UPLOAD ===================== -->
        <div class="card card-style6 card-static">
            <div class="card-header card-header-style2">
                <h4 class="secondary_heading_type2">Import Site Profile</h4>
            </div>
            <div class="card-body">
                <p class="pys-profile-intro">
                    Upload a Site Profile <code>.json</code> file exported from a PixelYourSite (Pro or Free) site.
                    Nothing changes until you review a preview and confirm.
                </p>
                <form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action"
                           value="<?php echo esc_attr( SiteProfileAdmin::IMPORT_UPLOAD_ACTION ); ?>">
					<?php wp_nonce_field( SiteProfileAdmin::IMPORT_UPLOAD_ACTION ); ?>
                    <div class="pys-profile-actions" style="margin-top:0;">
                        <input type="file" id="pys_profile_file" name="pys_profile_file" accept="application/json"
                               class="pys-profile-file-input">
                        <label for="pys_profile_file" class="pys-profile-btn pys-profile-btn--secondary">Choose
                            file</label>
                        <span class="pys-profile-file-name" id="pys-profile-file-name">No file selected</span>
                        <button type="submit" class="pys-profile-btn pys-profile-btn--primary">
                            <i class="icon-import"></i> Upload &amp; Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>

	<?php elseif ( ( $stage === 'configure' || $stage === 'dryrun' ) && $view ) :
		$summary = $view[ 'summary' ];
		?>

        <!-- ===================== FILE PREVIEW ===================== -->
        <div class="gap-24">
            <div class="card card-style6 card-static">
                <div class="card-header card-header-style2">
                    <h4 class="secondary_heading_type2">File preview</h4>
                </div>
                <div class="card-body">
                    <div class="pys-profile-preview">
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Source site</span>
							<?php echo esc_html( $summary[ 'source_site_url' ] ); ?>
                        </div>
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Edition</span>
							<?php echo esc_html( $summary[ 'plugin_edition' ] ); ?>
                        </div>
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Plugin version</span>
							<?php echo esc_html( $summary[ 'plugin_version' ] ); ?>
                        </div>
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Export format</span>
							<?php echo esc_html( $summary[ 'export_version' ] ); ?>
                        </div>
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Generated</span>
							<?php echo esc_html( $format_dt( $summary[ 'generated_at' ] ) ); ?>
                        </div>
                        <div class="pys-profile-preview__item">
                            <span class="pys-profile-preview__label">Add-ons in file</span>
							<?php echo esc_html(
								$summary[ 'installed_addons' ] ? implode( ', ', $summary[ 'installed_addons' ] ) : '—'
							); ?>
                        </div>
                    </div>
                </div>
            </div>

			<?php if ( $stage === 'configure' ) : ?>

                <!-- ===================== STAGE: CONFIGURE ===================== -->
                <form method="post" action="<?php echo esc_url( $post_url ); ?>" class="gap-24">
                    <input type="hidden" name="action"
                           value="<?php echo esc_attr( SiteProfileAdmin::IMPORT_DRYRUN_ACTION ); ?>">
					<?php wp_nonce_field( SiteProfileAdmin::IMPORT_DRYRUN_ACTION ); ?>

                    <!-- Domain / URL remapping -->
                    <div class="card card-style6 card-static">
                        <div class="card-header card-header-style2">
                            <h4 class="secondary_heading_type2">Domain / URL remapping</h4>
                        </div>
                        <div class="card-body">
                            <p class="pys-profile-intro">
                                Applied only to server container / transport URLs and Head &amp; Footer scripts.
                                The source site URL is pre-filled below — adjust it, clear it to skip, or add
                                extra staging&nbsp;→&nbsp;production mappings.
                            </p>

                            <div class="pys-profile-remap" id="pys-profile-remap">
                                <!-- Default source → target row (not removable). -->
                                <div class="pys-profile-remap__row">
                                    <input type="text" class="input-standard"
                                           name="pys_import[remap][0][from]"
                                           value="<?php echo esc_attr( $summary[ 'source_site_url' ] ); ?>"
                                           placeholder="Find (e.g. https://old-site.com)">
                                    <span class="pys-profile-remap__arrow">→</span>
                                    <input type="text" class="input-standard"
                                           name="pys_import[remap][0][to]"
                                           value="<?php echo esc_attr( site_url() ); ?>"
                                           placeholder="Replace with (e.g. https://new-site.com)">
                                    <span class="pys-profile-remap__remove-slot" aria-hidden="true"></span>
                                </div>
                            </div>

                            <div class="mt-16">
                                <button type="button" class="btn btn-sm btn-primary btn-primary-type2"
                                        id="pys-profile-remap-add">
                                    Add domain mapping
                                </button>
                            </div>

                            <template id="pys-profile-remap-template">
                                <div class="pys-profile-remap__row">
                                    <input type="text" class="input-standard"
                                           name="pys_import[remap][__i__][from]"
                                           placeholder="Find (e.g. https://staging.example.com)">
                                    <span class="pys-profile-remap__arrow">→</span>
                                    <input type="text" class="input-standard"
                                           name="pys_import[remap][__i__][to]"
                                           placeholder="Replace with (e.g. https://example.com)">
                                    <button type="button" class="button-remove-row pys-profile-remap__remove pys-profile-remap__remove-slot"
                                            aria-label="Remove mapping">
                                        <i class="fa fa-trash-o"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="card card-style6 card-static">
                        <div class="card-header card-header-style2">
                            <h4 class="secondary_heading_type2">Choose what to import</h4>
                        </div>
                        <div class="card-body">
                            <p class="pys-profile-intro">Every item defaults to <b>Skip</b> — nothing is applied unless
                                you choose an action.
                            </p>

                            <div class="gap-16">
								<?php foreach ( $view[ 'modules' ] as $m ) :
									$slug = $m[ 'slug' ];

									$has_body      = $m[ 'available' ]
									                 && ( ( $m[ 'type' ] === 'core'
									                        && !empty( $m[ 'has_automatic_events' ] ) )
									                      || ( $m[ 'type' ] === 'flat'
									                           && !empty( $m[ 'has_token_in_file' ] ) )
									                      || ( $m[ 'type' ] === 'pixel'
									                           && !empty( $m[ 'incoming_pixels' ] ) ) );
									$show_settings = $m[ 'available' ]
									                 && ( $m[ 'type' ] !== 'pixel'
									                      || !empty( $m[ 'has_core_settings' ] ) );
									?>
                                    <div class="card card-style6 <?php echo $has_body ? '' : 'card-static'; ?> <?php echo $m[ 'available' ] ? '' : 'pys-profile-muted'; ?>">

                                        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
                                            <div class="pys-profile-module-head d-flex align-items-center">
                                                <h4 class="secondary_heading_type2"><?php echo esc_html(
														$m[ 'label' ]
													); ?></h4>
												<?php if ( !$m[ 'available' ] ) :
													renderWarningMessage( $m[ 'reason' ] );
                                                elseif ( $show_settings ) : ?>
                                                    <div class="select-standard-wrap pys-profile-select">
                                                        <select name="pys_import[modules][<?php echo esc_attr(
															$slug
														); ?>][action]" class="select-standard">
                                                            <option value="skip">Skip settings</option>
                                                            <option value="overwrite">Overwrite settings</option>
                                                        </select>
                                                    </div>
												<?php endif; ?>
                                            </div>
											<?php if ( $has_body ) {
												cardCollapseSettings();
											} ?>
                                        </div>

										<?php if ( $has_body ) : ?>
                                            <div class="card-body">
                                                <div class="gap-16">

													<?php if ( $m[ 'type' ] === 'core' ) : ?>
                                                        <div class="small-checkbox">
                                                            <input type="checkbox" id="pys_import_ae"
                                                                   name="pys_import[core][automatic_events]" value="1"
                                                                   class="small-control-input">
                                                            <label class="small-control small-checkbox-label"
                                                                   for="pys_import_ae">
                                                            <span class="small-control-indicator"><i
                                                                        class="icon-check"></i></span>
                                                                <span class="small-control-description">Import Automatic Events (global toggles)</span>
                                                            </label>
                                                        </div>

													<?php elseif ( $m[ 'type' ] === 'flat' ) : ?>
                                                        <div class="small-checkbox">
                                                            <input type="checkbox"
                                                                   id="pys_import_flat_token_<?php echo esc_attr(
																       $slug
															       ); ?>"
                                                                   name="pys_import[flat_tokens][<?php echo esc_attr(
																       $slug
															       ); ?>]" value="1" class="small-control-input">
                                                            <label class="small-control small-checkbox-label"
                                                                   for="pys_import_flat_token_<?php echo esc_attr(
																       $slug
															       ); ?>">
                                                            <span class="small-control-indicator"><i
                                                                        class="icon-check"></i></span>
                                                                <span class="small-control-description">Import credentials / token</span>
                                                            </label>
                                                        </div>

										<?php elseif ( $m[ 'type' ] === 'pixel' ) : ?>
											<?php if ( !empty( $m[ 'incoming_pixels' ] ) ) : ?>
                                                            <div>
												<?php if ( count( $m[ 'incoming_pixels' ] ) > 1 ) : ?>
                                                                    <div class="pys-profile-subtitle">Pixels in file — choose a target for each</div>
												<?php endif; ?>
                                                                <div class="pys-profile-pixels">
													<?php foreach ( $m[ 'incoming_pixels' ] as $p ) : ?>
														<?php $render_import_pixel(
															$slug,
															$p,
															$m[ 'override_targets' ]
														); ?>
													<?php endforeach; ?>
                                                                </div>
                                                            </div>
											<?php endif; ?>
										<?php endif; ?>

                                                </div>
                                            </div>
										<?php endif; ?>
                                    </div>
								<?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="pys-profile-actions">
                        <button type="submit" class="pys-profile-btn pys-profile-btn--primary">
                            <i class="icon-settings"></i> Preview changes
                        </button>
                        <a href="<?php echo esc_url(
							wp_nonce_url(
								add_query_arg( 'action', SiteProfileAdmin::IMPORT_CANCEL_ACTION, $post_url ),
								SiteProfileAdmin::IMPORT_CANCEL_ACTION
							)
						); ?>" class="pys-profile-btn pys-profile-btn--secondary">Cancel</a>
                    </div>
                </form>

			<?php else : // dryrun
				$diff = isset( $import_ctx[ 'diff' ] ) ? $import_ctx[ 'diff' ] : array();
				?>

                <!-- ===================== STAGE: DRY-RUN DIFF ===================== -->
                <div class="card card-style6 card-static">
                    <div class="card-header card-header-style2">
                        <h4 class="secondary_heading_type2">Review changes (<?php echo count( $diff ); ?>)</h4>
                    </div>
                    <div class="card-body">
						<?php if ( empty( $diff ) ) : ?>
                            <p class="pys-profile-intro">No changes — the selected items match the current settings.</p>
						<?php else : ?>
                            <div class="pys-profile-diff-scroll">
                                <table class="widefat striped">
                                    <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Field</th>
                                        <th>Current</th>
                                        <th>New</th>
                                    </tr>
                                    </thead>
                                    <tbody>
									<?php foreach ( $diff as $row ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $row[ 'module' ] ); ?></td>
                                            <td>
												<?php echo esc_html( $row[ 'field' ] ); ?>
												<?php if ( $row[ 'module' ] === 'head_footer' ) : ?>
                                                    <span class="pys-profile-script-flag" style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:3px;background:#d63638;color:#fff;font-size:11px;">runs on every page</span>
												<?php endif; ?>
                                            </td>
                                            <td><?php if ( ! empty( $row[ 'sensitive' ] ) ) : ?><code>••••••••</code><?php elseif ( $row[ 'module' ] === 'head_footer' ) : ?><pre class="pys-profile-script-pre" style="margin:0;max-height:220px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-family:Consolas,Monaco,monospace;font-size:12px;"><?php echo esc_html( $render_val( $row[ 'old' ], true ) ); ?></pre><?php else : ?><code><?php echo esc_html( $render_val( $row[ 'old' ] ) ); ?></code><?php endif; ?>
                                            </td>
                                            <td><?php if ( ! empty( $row[ 'sensitive' ] ) ) : ?><code>••••••••</code><?php elseif ( $row[ 'module' ] === 'head_footer' ) : ?><pre class="pys-profile-script-pre" style="margin:0;max-height:220px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-family:Consolas,Monaco,monospace;font-size:12px;"><?php echo esc_html( $render_val( $row[ 'new' ], true ) ); ?></pre><?php else : ?><code><?php echo esc_html( $render_val( $row[ 'new' ] ) ); ?></code><?php endif; ?>
                                            </td>
                                        </tr>
									<?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
						<?php endif; ?>
                    </div>
                </div>

                <div class="pys-profile-actions">
                    <form method="post" action="<?php echo esc_url( $post_url ); ?>">
                        <input type="hidden" name="action"
                               value="<?php echo esc_attr( SiteProfileAdmin::IMPORT_APPLY_ACTION ); ?>">
						<?php wp_nonce_field( SiteProfileAdmin::IMPORT_APPLY_ACTION ); ?>
                        <button type="submit"
                                class="pys-profile-btn pys-profile-btn--primary" <?php echo empty( $diff ) ? 'disabled' : ''; ?>>
                            <i class="icon-check"></i> Confirm Import
                        </button>
                    </form>
                    <a href="<?php echo esc_url( SiteProfileAdmin::configureUrl() ); ?>"
                       class="pys-profile-btn pys-profile-btn--secondary">Back</a>
                </div>

			<?php endif; ?>
        </div>

	<?php elseif ( $stage === 'done' ) : ?>

        <!-- ===================== STAGE: DONE ===================== -->
        <div class="card card-style6 card-static">
            <div class="card-header card-header-style2">
                <h4 class="secondary_heading_type2">Import complete</h4>
            </div>
            <div class="card-body">
                <p class="pys-profile-intro">The selected settings were imported.</p>
                <div class="pys-profile-actions" style="margin-top:0;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SiteProfileAdmin::PAGE_SLUG ) ); ?>"
                       class="pys-profile-btn pys-profile-btn--secondary">Import another file</a>
                </div>
            </div>
        </div>

	<?php endif; ?>

	<?php
	$profile_backups = $import_ctx[ 'backups' ] ?? array();

	if ( ! empty( $profile_backups ) && in_array( $stage, array( 'upload', 'done' ), true ) ) :
		?>
        <div class="card card-style6 card-static pys-profile-message pys-profile-message--warning">
            <div class="card-header card-header-style2">
                <h4 class="secondary_heading_type2">Settings backup</h4>
            </div>
            <div class="card-body">
                <p class="pys-profile-intro">
                    Snapshots of your settings are saved before each import. Pick one and roll everything
                    back to that point — the most recent <?php echo (int) count( $profile_backups ); ?> are kept.
                </p>
                <form method="post" action="<?php echo esc_url( $post_url ); ?>" id="pys-profile-restore-form">
                    <input type="hidden" name="action"
                           value="<?php echo esc_attr( SiteProfileAdmin::IMPORT_RESTORE_ACTION ); ?>">
                    <?php wp_nonce_field( SiteProfileAdmin::IMPORT_RESTORE_ACTION ); ?>

                    <div class="pys-profile-backups gap-16 mb-24">
                        <?php foreach ( $profile_backups as $bk_index => $bk ) :
                            $bk_id     = $bk[ 'id' ] ?? '';
                            $bk_date   = $bk[ 'created_at' ] ?? '';
                            $bk_source = $bk[ 'source' ] ?? '';
                            $bk_mods   = ! empty( $bk[ 'modules' ] ) ? implode( ', ', (array) $bk[ 'modules' ] ) : '';
                            $bk_dom    = 'pys-backup-' . sanitize_html_class( (string) $bk_id );
                            ?>
                            <div class="small-checkbox">
                                <input type="radio" name="backup_id" id="<?php echo esc_attr( $bk_dom ); ?>"
                                       value="<?php echo esc_attr( $bk_id ); ?>" class="small-control-input" <?php checked( 0, $bk_index ); ?>>
                                <label class="small-control small-checkbox-label" for="<?php echo esc_attr( $bk_dom ); ?>">
                                    <span class="small-control-indicator"><i class="icon-check"></i></span>
                                    <span class="small-control-description">
                                        <?php
                                        echo $bk_date ? esc_html( $format_dt( $bk_date ) ) : esc_html( $bk_id );
                                        if ( $bk_source ) { echo ' — from <b>' . esc_html( $bk_source ) . '</b>'; }
                                        if ( $bk_mods )   { echo ' <span class="pys-profile-pixel__meta">' . esc_html( $bk_mods ) . '</span>'; }
                                        ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="pys-profile-btn pys-profile-btn--secondary" id="pys-profile-restore-btn">
                        Restore selected snapshot
                    </button>
                </form>
            </div>
        </div>
	<?php endif; ?>

	<?php
	$profile_audit = $import_ctx[ 'audit' ] ?? array();
	if ( ! empty( $profile_audit ) && in_array( $stage, array( 'upload', 'done' ), true ) ) :
		?>
        <div class="card card-style6 card-static">
            <div class="card-header card-header-style2">
                <h4 class="secondary_heading_type2">Recent activity</h4>
            </div>
            <div class="card-body">
                <div class="pys-profile-diff-scroll" style="max-height:360px;overflow:auto;">
                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>IP</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                        </thead>
                        <tbody>
						<?php foreach ( $profile_audit as $a ) : ?>
                            <tr>
                                <td><?php echo esc_html( $format_dt( $a[ 'ts' ] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( $a[ 'login' ] ?? '' ); ?></td>
                                <td><code><?php echo esc_html( $a[ 'ip' ] ?? '' ); ?></code></td>
                                <td><?php echo esc_html( $a[ 'action' ] ?? '' ); ?></td>
                                <td><?php echo esc_html( $a[ 'status' ] ?? '' ); ?></td>
                                <td><?php echo esc_html( $a[ 'note' ] ?? '' ); ?></td>
                            </tr>
						<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
	<?php endif; ?>
</div>

<script>
	( function () {
		// Reflect the chosen file name next to the styled "Choose file" button.
		let fileInput = document.getElementById( 'pys_profile_file' ),
		    fileName = document.getElementById( 'pys-profile-file-name' );
		if ( fileInput && fileName ) {
			fileInput.addEventListener( 'change', function () {
				fileName.textContent = ( fileInput.files && fileInput.files.length )
					? fileInput.files[ 0 ].name : 'No file selected';
			} );
		}

		document.querySelectorAll( '.pys-import-pixel-row' ).forEach( function ( row ) {
			let action = row.querySelector( '.pys-import-action' );
			if ( !action ) {
				return;
			}
			let target = row.querySelector( '.pys-import-target' ),
			    token  = row.querySelector( '.pys-import-token' );

			function sync() {
				if ( target ) {
					target.classList.toggle( 'is-visible', action.value === 'override' );
				}
				if ( token ) {
					token.classList.toggle( 'is-hidden', action.value === 'skip' );
				}
			}

			action.addEventListener( 'change', sync );
			sync();
		} );

		// Domain remap: add / remove custom mapping rows.
		let remapWrap = document.getElementById( 'pys-profile-remap' ),
		    remapAdd  = document.getElementById( 'pys-profile-remap-add' ),
		    remapTpl  = document.getElementById( 'pys-profile-remap-template' );
		if ( remapWrap && remapAdd && remapTpl ) {
			let remapIndex = 1;
			remapAdd.addEventListener( 'click', function () {
				let markup = remapTpl.innerHTML.replace( /__i__/g, remapIndex++ ),
				    holder = document.createElement( 'div' );
				holder.innerHTML = markup.trim();
				if ( holder.firstElementChild ) { remapWrap.appendChild( holder.firstElementChild ); }
			} );
			remapWrap.addEventListener( 'click', function ( e ) {
				let remove = e.target.closest( '.pys-profile-remap__remove' );
				if ( remove ) {
					let row = remove.closest( '.pys-profile-remap__row' );
					if ( row ) { row.remove(); }
				}
			} );
		}

		let restoreBtn = document.getElementById( 'pys-profile-restore-btn' ),
			restoreForm = document.getElementById( 'pys-profile-restore-form' );
		if ( restoreBtn && restoreForm ) {
			let restoreMsg = 'Restore the settings snapshot taken before the last import? Current settings for the affected modules will be replaced.';
			restoreBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				if ( window.jQuery && jQuery.confirm ) {
					jQuery.confirm( {
						title: 'Restore previous settings',
						content: '<p>' + restoreMsg + '</p>',
						type: 'pys',
						typeAnimated: true,
						buttons: {
							restore: {
								text: 'Yes, restore',
								btnClass: 'btn-pys btn-pys-red',
								action: function () {
									HTMLFormElement.prototype.submit.call( restoreForm );
								}
							},
							cancel: {
								text: 'No, keep current'
							}
						}
					} );
				} else if ( window.confirm( restoreMsg ) ) {
					HTMLFormElement.prototype.submit.call( restoreForm );
				}
			} );
		}
	} )();
</script>
