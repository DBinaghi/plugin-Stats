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

<div id="primary">
    <?php echo flash(); ?>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits in the last 30 days'); ?></h2>
			<canvas id="hitsLast30DaysChart" class="chart-canvas"></canvas>
			
			<?php
				$x_coordinates = [];
				$y_coordinates1 = [];
				$y_coordinates2 = [];
				foreach ($results['last30Days'] as $key => $values) {
					$x_coordinates[] = $key;
					$y_coordinates1[] = $values['anonymous'];
					$y_coordinates2[] = $values['identified'];
				}
			?>

			<script>
				new Chart("hitsLast30DaysChart", {
					type: "bar",
					data: {
						labels: <?= json_encode($x_coordinates); ?>,
						datasets: [{
							label: '<?= __('Anonymous'); ?>',
							data: <?= json_encode($y_coordinates1); ?>
						},
						{
							label: '<?= __('Identified'); ?>',
							data: <?= json_encode($y_coordinates2); ?>
						}]
					},
					options: {
						responsive: true,
						scales: {
							x: {
								stacked: true
							},
							y: {
								stacked: true,
								type: 'logarithmic'
							}
						},
						layout: {
							padding: {
								top: 10, // Adds 10px over the chart
								bottom: 10 // Adds 10px under the chart
							}
						}
					}
				});
			</script>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits in the last 12 months'); ?></h2>
			<canvas id="hitsLast12MonthsChart" class="chart-canvas"></canvas>

			<?php
				$x_coordinates = [];
				$y_coordinates1 = [];
				$y_coordinates2 = [];
				foreach ($results['last12Months'] as $key => $values) {
					$x_coordinates[] = $key;
					$y_coordinates1[] = $values['anonymous'];
					$y_coordinates2[] = $values['identified'];
				}
			?>

			<script>
				new Chart("hitsLast12MonthsChart", {
					type: "bar",
					data: {
						labels: <?= json_encode($x_coordinates); ?>,
						datasets: [{
							label: '<?= __('Anonymous'); ?>',
							data: <?= json_encode($y_coordinates1); ?>
						},
						{
							label: '<?= __('Identified'); ?>',
							data: <?= json_encode($y_coordinates2); ?>
						}]
					},
					options: {
						responsive: true,
						scales: {
							x: {
								stacked: true
							},
							y: {
								stacked: true,
								type: 'logarithmic'
							}
						},
						layout: {
							padding: {
								top: 10, // Adds 10px over the chart
								bottom: 10 // Adds 10px under the chart
							}
						}
					}
				});
			</script>
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Hits per year'); ?></h2>
			<canvas id="hitsPerYearChart" class="chart-canvas"></canvas>

			<?php
				$x_coordinates = [];
				$y_coordinates1 = [];
				$y_coordinates2 = [];
				foreach ($results['perYear'] as $key => $values) {
					$x_coordinates[] = $key;
					$y_coordinates1[] = $values['anonymous'];
					$y_coordinates2[] = $values['identified'];
				}
			?>

			<script>
				new Chart("hitsPerYearChart", {
					type: "bar",
					data: {
						labels: <?= json_encode($x_coordinates); ?>,
						datasets: [{
							label: '<?= __('Anonymous'); ?>',
							data: <?= json_encode($y_coordinates1); ?>
						},
						{
							label: '<?= __('Identified'); ?>',
							data: <?= json_encode($y_coordinates2); ?>
						}]
					},
					options: {
						responsive: true,
						scales: {
							x: {
								stacked: true
							},
							y: {
								stacked: true,
								type: 'logarithmic'
							}
						},
						layout: {
							padding: {
								top: 10, // Adds 10px over the chart
								bottom: 10 // Adds 10px under the chart
							}
						}
					}
				});
			</script>		
		</div>
	</section>

	<section class="ten columns alpha omega">
		<div class="panel">
			<h2><?php echo __('Most frequent accepted languages'); ?></h2>
			<canvas id="acceptLanguageChart" class="chart-canvas"></canvas>
			
			<?php
				$coordinates = [];
				foreach ($results['most_frequent_fields']['accept_language'] as $key => $result) {
					$string = $result['accept_language'];
					$language = strtolower(substr(explode(';', $string)[0], 0, 2));
					if (isset($coordinates[$language])) {
						$coordinates[$language] += $result['hits'];
					} else {
						$coordinates[$language] = $result['hits'];
					}
				}
				arsort($coordinates); // sorts array by value DESC
				$coordinates = array_slice($coordinates, 0, 24); // max 24 elements in the chart
			?>
					
			<script>
				new Chart("acceptLanguageChart", {
					type: "pie",
					data: {
						labels: <?= json_encode(array_keys($coordinates)); ?>,
						datasets: [{
							data: <?= json_encode(array_values($coordinates)); ?>,
							hoverOffset: 15 // Pixels the segment expands on hover
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: {
								position: 'right',   // Legend to the right
								align: 'center',
								labels: {
									generateLabels: (chart) => {
										const datasets = chart.data.datasets;
										return chart.data.labels.map((label, i) => ({
											text: `${label}: ${datasets[0].data[i]}`, // Combines label and value
											fillStyle: datasets[0].backgroundColor[i], // Mantains the color
											index: i,
											// Other properties needed to preserve clickability
											hidden: !chart.getDataVisibility(i),
											strokeStyle: datasets[0].borderColor ? datasets[0].borderColor[i] : '#fff',
											lineWidth: datasets[0].borderWidth || 0,
										}));
									}
								}
							}
						},
						layout: {
							padding: {
								top: 10, // Adds 10px over the chart
								bottom: 10 // Adds 10px under the chart
							}
						}
					}
				});
			</script>
		</div>
	</section>

	<?php fire_plugin_hook('stats_summary_charts', array('view' => $this)); ?>
</div>

<?php echo foot(); ?>
