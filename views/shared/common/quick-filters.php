<?php
$base_url = 'stats/browse/by-' . $stats_type;
$current  = html_escape(current_url());
?>
<select name="quick-filter" class="quick-filter" aria-label="<?php echo __('Quick Filter'); ?>"
        onchange="if(this.value) window.location=this.value;">
    <option><?php echo __('Quick Filter'); ?></option>
<?php switch ($stats_type):
    case 'page': ?>
    <option value="<?php echo url($base_url); ?>"><?php echo __('View All'); ?></option>
    <option value="<?php echo url($base_url, array('has_record' => true)); ?>"><?php echo __('With record'); ?></option>
    <option value="<?php echo url($base_url, array('has_record' => false)); ?>"><?php echo __('Without record'); ?></option>
    <?php break;

    case 'record': ?>
    <option value="<?php echo url($base_url); ?>"><?php echo __('View All'); ?></option>
    <option value="<?php echo url($base_url, array('record_type' => 'Item')); ?>"><?php echo __('By Item'); ?></option>
    <option value="<?php echo url($base_url, array('record_type' => 'Collection')); ?>"><?php echo __('By Collection'); ?></option>
    <option value="<?php echo url($base_url, array('record_type' => 'File')); ?>"><?php echo __('By File'); ?></option>
    <?php if (plugin_is_active('SimplePages')): ?>
    <option value="<?php echo url($base_url, array('record_type' => 'SimplePagesPage')); ?>"><?php echo __('By Simple Page'); ?></option>
    <?php endif; ?>
    <?php if (plugin_is_active('ExhibitBuilder')): ?>
    <option value="<?php echo url($base_url, array('record_type' => 'Exhibit')); ?>"><?php echo __('By Exhibit'); ?></option>
    <option value="<?php echo url($base_url, array('record_type' => 'ExhibitPage')); ?>"><?php echo __('By Exhibit Page'); ?></option>
    <?php endif; ?>
    <?php break;

    case 'download': ?>
    <option value="<?php echo url($base_url); ?>"><?php echo __('View All'); ?></option>
    <?php break;

    case 'field': ?>
    <option value="<?php echo url($base_url, array('field' => 'referrer')); ?>"><?php echo __('Referrers'); ?></option>
    <option value="<?php echo url($base_url, array('field' => 'query')); ?>"><?php echo __('Queries'); ?></option>
    <option value="<?php echo url($base_url, array('field' => 'accept_language')); ?>"><?php echo __('Languages'); ?></option>
    <option value="<?php echo url($base_url, array('field' => 'user_agent')); ?>"><?php echo __('Browsers'); ?></option>
    <?php break;
endswitch; ?>
</select>
