<?php
/**
 * Copyright (c) 2014 Khang Minh <betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * BWP Polldaddy Widget Class
 */
class BWP_PollDaddy_Widget extends WP_Widget
{
	function BWP_PollDaddy_Widget()
	{
		$widget_ops = array(
			'classname' => 'bwp-polldaddy-widget',
			'description' => __('Show Polldaddy polls on your blog using widgets.', 'bwp-polldaddy')
		);

		$control_ops = array(
			'width' => 300
		);

		$this->WP_Widget('bwp_polldaddy', __('BWP Polldaddy Polls', 'bwp-polldaddy'), $widget_ops, $control_ops);
	}

	function widget($args, $instance)
	{
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title'])
			? __('Polls', 'bwp-polldaddy')
			: $instance['title'], $instance, $this->id_base
		);

		echo $before_widget;

		if ($title)
			echo $before_title . $title . $after_title . "\n";

		$instance['id'] = $instance['poll_id'];
		bwp_polldaddy($instance);

		echo $after_widget;
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;

		$instance['title']   = strip_tags($new_instance['title']);
		$instance['poll_id'] = strip_tags($new_instance['poll_id']);
		$instance['orderby'] = $new_instance['orderby'];
		$instance['order']   = $new_instance['order'];
		$instance['limit']   = (int) $new_instance['limit'];

		return $instance;
	}

	function form($instance)
	{
		$instance = wp_parse_args(
			(array) $instance, array(
				'poll_id' => '',
				'limit'   => 1,
				'orderby' => 'id',
				'order'   => 'desc'
			)
		);

		$title = isset($instance['title']) ? strip_tags($instance['title']) : '';
		$limit = (int) $instance['limit'];
		$id    = $instance['poll_id'];
?>
		<div class="bwp-polldaddy-widget-control">
		<p><?php _e('<strong>Note:</strong> All inputs are optional.', 'bwp-polldaddy'); ?></p>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'bwp-polldaddy'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<p>
			<label for="<?php echo $this->get_field_id('poll_id'); ?>"><?php _e('Show just one poll with ID:', 'bwp-polldaddy'); ?></label>
			<input class="smallfat" id="<?php echo $this->get_field_id('poll_id'); ?>" name="<?php echo $this->get_field_name('poll_id'); ?>" type="text" value="<?php echo esc_attr($id); ?>" /></p>
		<p>
			<label for="<?php echo $this->get_field_id('orderby'); ?>"><?php _e('Order polls', 'bwp-polldaddy'); ?></label>
			<select id="<?php echo $this->get_field_id('orderby'); ?>" name="<?php echo $this->get_field_name('orderby'); ?>">
				<option value=""><?php _e('By poll ID', 'bwp-polldaddy'); ?></option>
				<option value="created" <?php selected($instance['orderby'], 'created' ); ?>><?php _e('By time of creation', 'bwp-polldaddy'); ?></option>
				<option value="responses" <?php selected($instance['orderby'], 'responses' ); ?>><?php _e('By number of responses', 'bwp-polldaddy'); ?></option>
				<option value="rand" <?php selected($instance['orderby'], 'rand' ); ?>><?php _e('Randomly', 'bwp-polldaddy'); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('order'); ?>"><?php _e('Order them', 'bwp-polldaddy'); ?></label>
			<select id="<?php echo $this->get_field_id('order'); ?>" name="<?php echo $this->get_field_name('order'); ?>">
				<option value="desc" <?php selected($instance['order'], 'desc' ); ?>><?php _e('Descendingly', 'bwp-polldaddy'); ?></option>
				<option value="asc" <?php selected($instance['order'], 'asc' ); ?>><?php _e('Ascendingly', 'bwp-polldaddy'); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Show at most'); ?> <input class="smallfat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo esc_attr($limit); ?>" /> <?php _e('poll(s) (zero for no limit).', 'bwp-polldaddy'); ?></label>
		</p>
		</div>
<?php
	}
}

function bwp_polldaddy_register_widget()
{
	register_widget('BWP_PollDaddy_Widget');
}
