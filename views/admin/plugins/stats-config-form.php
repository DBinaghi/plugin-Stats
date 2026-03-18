<style>
	.radio_btn {
		margin-bottom: .7em !important;
	}
	
	.stats-rights {
		margin-bottom: 50px;
	}

	.stats-rights th {
		text-align: center;
	}

	.stats-rights td {
		vertical-align: middle;
	}
	
	.stats-rights td:not(:first-child) {
		text-align: center;
	}
	
	.input-block ul {
		list-style-type: none;
		margin-top: 0;
		margin-bottom: 0;
		padding: 0;
	}
	
	.input-block li {
		margin-bottom: 10px;
	}
	
	.seven.columns.omega {
		margin-left: 0;
	}
	
	input[type="checkbox"] + span {
		line-height: 1.5em;
	}

	/* UA Parser batch tool */
	#stats-ua-batch-progress {
		display: none;
		margin-top: 10px;
	}
	#stats-ua-batch-bar-wrap {
		background: #e0e0e0;
		border-radius: 3px;
		height: 18px;
		width: 100%;
		margin-bottom: 6px;
	}
	#stats-ua-batch-bar {
		background: #0073aa;
		height: 18px;
		border-radius: 3px;
		width: 0%;
		transition: width 0.3s;
	}
	#stats-ua-batch-status {
		font-style: italic;
		color: #555;
	}
</style>

<fieldset id="fieldset-stats-rights">
	<legend><?php echo __('Rights and Roles'); ?></legend>
	<p class="explanation">
		<?php 
			echo __('Select access rights for each stats page and each role.');
			echo ' ' . __('If "public" is checked, all people will have access to the selected data.');
			echo ' ' . __('To get stats about direct download of original files, a line should be added in ".htaccess".');
			echo '<br />' . __("%sWarning%s: Shortcodes, helpers and hooks don't follow any rule.", '<strong>', '</strong>'); 
		?>
	</p>
	<div class="field">
		<div class="seven columns omega">
			<div class="input-block">
				<?php
					$table = array(
						'summary' => array(
							'label' => __('View Summary'),
							'public' => 'stats_public_allow_summary',
							'roles' => 'stats_roles_summary',
						),
						'browse_pages' => array(
							'label' => __('Browse by Page'),
							'public' => 'stats_public_allow_browse_pages',
							'roles' => 'stats_roles_browse_pages',
						),
						'browse_records' => array(
							'label' => __('Browse by Record'),
							'public' => 'stats_public_allow_browse_records',
							'roles' => 'stats_roles_browse_records',
						),
						'browse_downloads' => array(
							'label' => __('Browse by Download'),
							'public' => 'stats_public_allow_browse_downloads',
							'roles' => 'stats_roles_browse_downloads',
						),
						'browse_fields' => array(
							'label' => __('Browse by Field'),
							'public' => 'stats_public_allow_browse_fields',
							'roles' => 'stats_roles_browse_fields',
						),
						'browse_collections' => array(
							'label' => __('Browse by Collection'),
							'public' => 'stats_public_allow_browse_collections',
							'roles' => 'stats_roles_browse_collections',
						),
					);
					$userRoles = get_user_roles();
					unset($userRoles['super']);
				?>
				<table class="stats-rights">
					<thead>
						<tr>
							<th></th>
							<th><?php echo __('Public'); ?></th>
							<?php foreach ($userRoles as $role => $label): ?>
							<th><?php echo $label; ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php
							$key = 0;
							foreach ($table as $name => $right):
								$currentRole = $right['roles'];
								$currentRoles = get_option($currentRole) ? unserialize(get_option($currentRole)) : array();
								printf('<tr class="%s">', (++$key % 2 == 1) ? 'odd' : 'even');
								echo '<td>' . $right['label'].  '</td>';
								echo '<td>';
								echo $this->formCheckbox($right['public'], true,
								array('checked' => (bool) get_option($right['public'])));
								echo '</td>';
								foreach ($userRoles as $role => $label):
									echo '<td>';
									echo $this->formCheckbox($currentRole . '[]', $role,
									array('checked' => in_array($role, $currentRoles) ? 'checked' : ''));
									echo '</td>';
								endforeach;
								echo '</tr>';
							endforeach;
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-per-page">
	<legend><?php echo __('User Status'); ?></legend>
	<p class="explanation">
		<?php
			echo __('These options allow to restrict stats according to status of users.')
				. ' ' . __('They are used with hooks, helpers and shortcodes, not with direct queries.');
		?>
	</p>
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_default_user_status_admin', __('User status for admin pages')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Choose the default status of users for stats in admin pages.'); ?>
			</p>
			<?php 
				echo $this->formRadio('stats_default_user_status_admin',
				get_option('stats_default_user_status_admin'),
				array('class' => 'radio_btn'),
				array(
					'hits' => __('Total hits'),
					'hits_anonymous' => __('Anonymous'),
					'hits_identified' => __('Identified users'),
				)); 
			?>
		</div>
	</div>
	
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_default_user_status_public', __('User status for public pages')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Choose the status of users to restrict stats in public pages.'); ?>
			</p>
			<?php 
				echo $this->formRadio('stats_default_user_status_public',
				get_option('stats_default_user_status_public'),
				array('class' => 'radio_btn'),
				array(
					'hits' => __('Total hits'),
					'hits_anonymous' => __('Anonymous'),
					'hits_identified' => __('Identified users'),
				)); 
			?>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-display">
	<legend><?php echo __('View Stats'); ?></legend>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_per_page_admin', __('Results Per Page (admin)')); ?>
		</div>
		<div class="inputs five columns omega">
			<div class="input-block">
				<p class="explanation">
					<?php echo __('Limit the number of results displayed per page in the administrative interface.'); ?>
				</p>
				<?php 
					echo $this->formText('stats_per_page_admin',
					get_option('stats_per_page_admin')); 
				?>
			</div>
		</div>
	</div>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_per_page_public', __('Results Per Page (public)')); ?>
		</div>
		<div class="inputs five columns omega">
			<div class="input-block">
				<p class="explanation">
					<?php echo __('Limit the number of results displayed per page in the public interface.'); ?>
				</p>
				<?php 
					echo $this->formText('stats_per_page_public',
					get_option('stats_per_page_public')); 
				?>
			</div>
		</div>
	</div>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_display_charts', __('Show Charts')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Displays extra tab with charts for some selected stats (Admin side).'); ?>
			</p>
			<?php 
				echo $this->formCheckbox('stats_display_charts', true, 
				array('checked' => (bool) get_option('stats_display_charts')));
			?>
		</div>
	</div>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_display_pagination_bottom', __('Display Bottom Pagination')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Displays pagination links also under the tables.'); ?>
			</p>
			<?php 
				echo $this->formCheckbox('stats_display_pagination_bottom', true, 
				array('checked' => (bool) get_option('stats_display_pagination_bottom')));
			?>
		</div>
	</div>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_display_quickfilter_bottom', __('Display Bottom Quick Filter')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Displays quick filter also under the tables.'); ?>
			</p>
			<?php 
				echo $this->formCheckbox('stats_display_quickfilter_bottom', true, 
				array('checked' => (bool) get_option('stats_display_quickfilter_bottom')));
			?>
		</div>
	</div>

	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_sortexaequo', __('Sort Ex-Aequo')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo __('Adds an additional sorting condition, for ex-aequo cases (warning: can considerably slow down the page loading time).'); ?>
			</p>
			<?php 
				echo $this->formCheckbox('stats_sortexaequo', true, 
				array('checked' => (bool) get_option('stats_sortexaequo')));
			?>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-display-by-hooks" style="margin-top: 2em">
	<legend><?php echo __('Display by Hooks'); ?></legend>
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_display_by_hooks', __('Hooks Showing Hits')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php 
					echo __('Select the hooks that will display hits in specific pages.');
					echo ' ' . __('In any case, it is the theme to decide last if hits are displayed or not.'); 
				?>
			</p>
			<div class="input-block">
				<ul>
				<?php
					foreach ($displayByHooks as $page) {
						echo '<li>';
						echo $this->formCheckbox('stats_display_by_hooks[]', $page,
						array('checked' => in_array($page, $displayByHooksSelected) ? 'checked' : ''));
						echo $page;
						echo '</li>';
					}
				?>
				</ul>
			</div>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-privacy">
	<legend><?php echo __('Privacy'); ?></legend>
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_privacy', __('Level of Privacy')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php 
					echo __('Choose the level of privacy (default: hashed IP).')
					. ' ' . __('A change applies only to new hits.');
				?>
			</p>
			<?php 
				echo $this->formRadio('stats_privacy',
				get_option('stats_privacy'),
				array('class' => 'radio_btn'),
				array(
					'anonymous' => __('Anonymous'),
					'hashed' => __('Hashed IP'),
					'partial_1' => __('Partial IP (first hex)'),
					'partial_2' => __('Partial IP (first 2 hexs)'),
					'partial_3' => __('Partial IP (first 3 hexs)'),
					'clear' => __('Clear IP'),
				)); 
			?>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-misc">
	<legend><?php echo __('Misc'); ?></legend>
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_excludebots', __('Exclude crawlers/bots')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo '<span>' . __('All hits which user agent contains the term "bot", "crawler", "spider", etc. will be excluded.') . '</span>'; ?>
			</p>
			<?php 
				echo $this->formCheckbox('stats_excludebots', true, 
				array('checked' => (bool) get_option('stats_excludebots')));
			?>
		</div>
	</div>
</fieldset>

<fieldset id="fieldset-stats-ua-parser">
	<legend><?php echo __('User Agent Parser'); ?></legend>
	<p class="explanation">
		<?php
			echo __('The plugin can parse raw user-agent strings into structured data (browser, OS, device type) using the %sua-parser-js%s library.',
				'<a href="https://github.com/faisalman/ua-parser-js" target="_blank">', '</a>');
			echo ' ';
			echo __('Parsing is done client-side by the visitor\'s browser and the result is cached server-side, so each unique user-agent string is parsed only once.');
		?>
	</p>

	<!-- Enable on-hit parsing -->
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_ua_parse_on_hit', __('Parse On Visit')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php echo '<span>' . __('Automatically parse the visitor\'s user-agent on each page view and cache the result.') . '</span>'; ?>
			</p>
			<?php
				echo $this->formCheckbox('stats_ua_parse_on_hit', true,
				array('checked' => (bool) get_option('stats_ua_parse_on_hit')));
			?>
		</div>
	</div>

	<!-- CDN / custom URL -->
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_ua_parser_url', __('URL Online Parser')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php
					echo __('URL of the online ua-parser-js script to load on public pages.');
					echo ' ';
					echo __('Leave empty to use the bundled version shipped with the plugin (recommended for offline / intranet installations).');
					echo '<br />';
					echo __('Example CDN URL: %s',
						'<code>https://cdn.jsdelivr.net/npm/ua-parser-js/src/ua-parser.min.js</code>');
				?>
			</p>
			<?php
				echo $this->formText('stats_ua_parser_url',
					get_option('stats_ua_parser_url'),
					array('size' => 80, 'placeholder' => 'https://cdn.jsdelivr.net/npm/ua-parser-js/src/ua-parser.min.js'));
			?>
		</div>
	</div>

	<!-- Batch parse existing hits -->
	<div class="field">
		<div class="two columns alpha">
			<?php echo $this->formLabel('stats_ua_parse_now', __('Parse Existing Hits')); ?>
		</div>
		<div class="inputs five columns omega">
			<p class="explanation">
				<?php
					$unparsed = isset($unparsedUaCount) ? (int) $unparsedUaCount : 0;
					if ($unparsed === 0) {
						echo '<strong>' . __('All user-agent strings in the hit log have already been parsed.') . '</strong>';
					} else {
						echo sprintf(
							__('There are %s<strong>%d</strong>%s distinct user-agent string(s) in the hit log that have not yet been parsed.'),
							'', $unparsed, ''
						);
					}
				?>
			</p>
			<?php if ($unparsed > 0): ?>
			<button type="button" id="stats-ua-batch-start" class="btn">
				<?php echo __('Parse all strings', $unparsed); ?>
			</button>
			<div id="stats-ua-batch-progress">
				<div id="stats-ua-batch-bar-wrap">
					<div id="stats-ua-batch-bar"></div>
				</div>
				<span id="stats-ua-batch-status"></span>
			</div>
			<?php endif; ?>
		</div>
	</div>
</fieldset>

<?php if (isset($unparsedUaCount) && $unparsedUaCount > 0): ?>
<script>
(function () {
    'use strict';

    var btn        = document.getElementById('stats-ua-batch-start');
    var progress   = document.getElementById('stats-ua-batch-progress');
    var bar        = document.getElementById('stats-ua-batch-bar');
    var statusEl   = document.getElementById('stats-ua-batch-status');

    if (!btn) return;

    // Endpoints (absolute URLs so they work regardless of base path).
    var batchUrl = '<?php echo url('stats/summary/parse-batch'); ?>';
    var storeUrl = '<?php echo url('stats/summary/store-batch'); ?>';

    // How many UA strings to parse + POST in one go.
    var CHUNK_SIZE = 20;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        progress.style.display = 'block';
        statusEl.textContent   = '<?php echo __('Loading user-agent list…'); ?>';

        // Step 1: ask the server for the list of unparsed UA strings.
        fetch(batchUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.user_agents || !data.user_agents.length) {
                    statusEl.textContent = '<?php echo __('Nothing to parse.'); ?>';
                    return;
                }
                processBatch(data.user_agents, 0);
            })
            .catch(function (e) {
                statusEl.textContent = '<?php echo __('Error loading list: '); ?>' + e.message;
            });
    });

    /**
     * Parse a chunk of UA strings client-side with ua-parser-js, then POST
     * the results to the server. Recurse until done.
     *
     * @param {string[]} list   Full list of unparsed UA strings.
     * @param {number}   offset Current position in the list.
     */
    function processBatch(list, offset) {
        var total = list.length;
        var chunk = list.slice(offset, offset + CHUNK_SIZE);

        if (!chunk.length) {
            bar.style.width      = '100%';
            statusEl.textContent = '<?php echo __('Done!'); ?>';
            btn.textContent      = '<?php echo __('All parsed'); ?>';
            return;
        }

        // Update progress bar.
        var pct = Math.round((offset / total) * 100);
        bar.style.width      = pct + '%';
        statusEl.textContent = offset + ' / ' + total;

        // ua-parser-js must already be loaded (it was injected by the plugin
        // on the admin page too — or we load it here if needed).
        ensureParser(function () {
            var items = chunk.map(function (ua) {
                var parser = new UAParser(ua);
                var res    = parser.getResult();
                var device = res.device || {};
                var botPat = /bot|crawler|spider|slurp|check_http|curl|wget|python|java\/|libwww/i;

                return {
                    user_agent : ua,
                    parsed     : {
                        browser          : (res.browser  && res.browser.name)    || '',
                        browser_version  : (res.browser  && res.browser.version) || '',
                        engine           : (res.engine   && res.engine.name)     || '',
                        engine_version   : (res.engine   && res.engine.version)  || '',
                        os               : (res.os       && res.os.name)         || '',
                        os_version       : (res.os       && res.os.version)      || '',
                        device_type      : device.type   || 'desktop',
                        device_vendor    : device.vendor || '',
                        device_model     : device.model  || '',
                        is_bot           : botPat.test(ua) ? 1 : 0
                    }
                };
            });

            // POST this chunk to the server.
            fetch(storeUrl, {
                method      : 'POST',
                headers     : { 'Content-Type': 'application/json' },
                credentials : 'same-origin',
                body        : JSON.stringify({ items: items })
            })
            .then(function (r) { return r.json(); })
            .then(function () {
                processBatch(list, offset + CHUNK_SIZE);
            })
            .catch(function (e) {
                statusEl.textContent = '<?php echo __('Error: '); ?>' + e.message;
            });
        });
    }

    /**
     * Load ua-parser-js if not already present, then call callback.
     */
    function ensureParser(callback) {
        if (typeof UAParser === 'function') {
            callback();
            return;
        }
        // Prefer the configured URL; fall back to the bundled copy.
        var src = (window.StatsUAConfig && window.StatsUAConfig.scriptUrl)
            ? window.StatsUAConfig.scriptUrl
            : '<?php echo WEB_ROOT . "/plugins/Stats/views/shared/javascripts/ua-parser.min.js" ?>';

		var s    = document.createElement('script');
        s.src    = src;
        s.onload = callback;
        s.onerror = function () {
            statusEl.textContent = '<?php echo __('Could not load ua-parser.min.js. Check the URL in the settings above.'); ?>';
        };
        document.head.appendChild(s);
    }
}());
</script>
<?php endif; ?>
