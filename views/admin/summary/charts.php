<?php
	$pageTitle = __('Stats');
	queue_css_file('stats');
	queue_js_file('chart.umd', 'javascripts');

	echo head(array(
		'title' => $pageTitle,
		'bodyclass' => 'stats index',
		'content_class' => 'horizontal-nav',
	));

	echo common('stats-nav');
?>

<style>
.chart-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 80px;
	color: #888;
	font-style: italic;
}
.chart-spinner {
	display: inline-block;
	width: 20px;
	height: 20px;
	margin-right: 10px;
	border: 3px solid #ddd;
	border-top-color: #555;
	border-radius: 50%;
	animation: chart-spin 0.8s linear infinite;
}
@keyframes chart-spin {
	to { transform: rotate(360deg); }
}
</style>

<div id="primary">
    <?php echo flash(); ?>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits in the last 30 days'); ?></h2>
			<div id="loading-days" class="chart-loading"><span class="chart-spinner"></span><?php echo __('Loading...'); ?></div>
			<canvas id="hitsLast30DaysChart" class="chart-canvas" style="display:none"></canvas>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits in the last 12 months'); ?></h2>
			<div id="loading-months" class="chart-loading"><span class="chart-spinner"></span><?php echo __('Loading...'); ?></div>
			<canvas id="hitsLast12MonthsChart" class="chart-canvas" style="display:none"></canvas>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits per year'); ?></h2>
			<div id="loading-years" class="chart-loading"><span class="chart-spinner"></span><?php echo __('Loading...'); ?></div>
			<canvas id="hitsPerYearChart" class="chart-canvas" style="display:none"></canvas>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Most frequent browsers'); ?></h2>
			<div id="loading-browsers" class="chart-loading"><span class="chart-spinner"></span><?php echo __('Loading...'); ?></div>
			<canvas id="browsersChart" class="chart-canvas" style="display:none"></canvas>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Most frequent accepted languages'); ?></h2>
			<div id="loading-languages" class="chart-loading"><span class="chart-spinner"></span><?php echo __('Loading...'); ?></div>
			<canvas id="acceptLanguageChart" class="chart-canvas" style="display:none"></canvas>
		</div>
	</section>

	<?php fire_plugin_hook('stats_summary_charts', array('view' => $this)); ?>
</div>

<script>
(function () {

	var BASE = '<?php echo html_escape(url('stats/summary')); ?>';

	function showChart(canvasId, loadingId) {
		var loading = document.getElementById(loadingId);
		loading.style.setProperty('display', 'none', 'important');
		document.getElementById(canvasId).style.display = 'block';
	}

	// --- Shared bar chart options ---
	function barOptions() {
		return {
			responsive: true,
			scales: {
				x: { stacked: true },
				y: { stacked: true, type: 'logarithmic' }
			},
			layout: { padding: { top: 10, bottom: 10 } }
		};
	}

	// --- Shared pie options ---
	function pieOptions() {
		return {
			responsive: true,
			plugins: { legend: pieLegend() },
			layout: { padding: { top: 10, bottom: 10 } }
		};
	}

	// --- Shared pie legend ---
	function pieLegend() {
		return {
			position: 'right',
			align: 'center',
			labels: {
				generateLabels: function (chart) {
					var datasets = chart.data.datasets;
					return chart.data.labels.map(function (label, i) {
						return {
							text: label + ': ' + datasets[0].data[i],
							fillStyle: datasets[0].backgroundColor[i],
							index: i,
							hidden: !chart.getDataVisibility(i),
							strokeStyle: datasets[0].borderColor ? datasets[0].borderColor[i] : '#fff',
							lineWidth: datasets[0].borderWidth || 0,
						};
					});
				}
			}
		};
	}

	// --- Browser palette ---
	// Shared palette for all pie charts.
	// First 7 match Chart.js default brand colors; black is reserved for "not identified".
	var PALETTE = [
		'#36A2EB', // blue        (Chart.js #1)
		'#FF6384', // pink-red    (Chart.js #2)
		'#FF9F40', // orange      (Chart.js #3)
		'#FFCD56', // yellow      (Chart.js #4)
		'#4BC0C0', // teal        (Chart.js #5)
		'#9966FF', // purple      (Chart.js #6)
		'#C9CBCF', // grey        (Chart.js #7)
		'#E6194B', // crimson
		'#3CB44B', // green
		'#000075', // navy
		'#F032E6', // magenta
		'#469990', // dark teal
		'#9A6324', // brown
		'#800000', // maroon
		'#4363D8', // cobalt blue
		'#808000', // olive
		'#911EB4', // violet
		'#42D4F4', // cyan
		'#F58231', // dark orange
		'#3D9970'  // dark green
	];
	var BLACK = '#000000';

	// Assign palette colors sequentially, optionally reserving black for one index.
	function paletteColors(count, reserveIndex) {
		var pi = 0;
		return Array.from({length: count}, function (_, i) {
			if (reserveIndex !== undefined && i === reserveIndex) return BLACK;
			return PALETTE[pi++ % PALETTE.length];
		});
	}

	// --- Fetch helpers ---
	function fetchChart(endpoint, callback) {
		fetch(BASE + '/' + endpoint)
			.then(function (r) { return r.json(); })
			.then(callback)
			.catch(function (e) { console.error(endpoint, e); });
	}

	// 1. Last 30 days
	fetchChart('chart-days', function (d) {
		showChart('hitsLast30DaysChart', 'loading-days');
		new Chart('hitsLast30DaysChart', {
			type: 'bar',
			data: {
				labels: d.labels,
				datasets: [
					{ label: '<?php echo __('Anonymous'); ?>', data: d.anonymous },
					{ label: '<?php echo __('Identified'); ?>', data: d.identified }
				]
			},
			options: barOptions()
		});
	});

	// 2. Last 12 months
	fetchChart('chart-months', function (d) {
		showChart('hitsLast12MonthsChart', 'loading-months');
		new Chart('hitsLast12MonthsChart', {
			type: 'bar',
			data: {
				labels: d.labels,
				datasets: [
					{ label: '<?php echo __('Anonymous'); ?>', data: d.anonymous },
					{ label: '<?php echo __('Identified'); ?>', data: d.identified }
				]
			},
			options: barOptions()
		});
	});

	// 3. Per year
	fetchChart('chart-years', function (d) {
		showChart('hitsPerYearChart', 'loading-years');
		new Chart('hitsPerYearChart', {
			type: 'bar',
			data: {
				labels: d.labels,
				datasets: [
					{ label: '<?php echo __('Anonymous'); ?>', data: d.anonymous },
					{ label: '<?php echo __('Identified'); ?>', data: d.identified }
				]
			},
			options: barOptions()
		});
	});

	// 4. Browsers
	fetchChart('chart-browsers', function (d) {
		if (!d.labels || d.labels.length === 0) {
			document.getElementById('loading-browsers').textContent = '<?php echo __('None'); ?>';
			return;
		}
		var notIdentifiedIndex = d.labels.indexOf(d.notIdentifiedLabel);
		var colors = paletteColors(d.labels.length, notIdentifiedIndex === -1 ? undefined : notIdentifiedIndex);
		showChart('browsersChart', 'loading-browsers');
		new Chart('browsersChart', {
			type: 'pie',
			data: {
				labels: d.labels,
				datasets: [{ data: d.data, backgroundColor: colors, hoverOffset: 15 }]
			},
			options: pieOptions()
		});
	});

	// 5. Languages
	fetchChart('chart-languages', function (d) {
		showChart('acceptLanguageChart', 'loading-languages');
		new Chart('acceptLanguageChart', {
			type: 'pie',
			data: {
				labels: d.labels,
				datasets: [{ data: d.data, backgroundColor: paletteColors(d.labels.length), hoverOffset: 15 }]
			},
			options: pieOptions()
		});
	});

}());
</script>

<?php echo foot(); ?>
