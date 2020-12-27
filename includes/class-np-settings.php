<?php
defined('ABSPATH') or die;

class NpSettings {

    public static $options;
    public static $defaultOptions;

    /**
     * Initialize options and defaults
     */
    public static function init() {
        self::$options = array(
            array(
                'name' => __('Create new page with Nicepage editor', 'nicepage'),
                'type' => 'buttonNp',
            ),
            array(
                'name' => __('jQuery', 'nicepage'),
                'type' => 'heading',
            ),
            array(
                'id'   => 'np_include_jquery',
                'name' => __('Use jQuery from plugin', 'nicepage'),
                'type' => 'checkbox',
            ),
            array(
                'name' => __('Responsive', 'nicepage'),
                'type' => 'heading',
            ),
            array(
                'id'   => 'np_auto_responsive',
                'name' => __('Auto Responsive', 'nicepage'),
                'type' => 'checkbox',
                'desc' => '<p>Nicepage content width will be adjusted to fit the content area of the theme.</p>
                           <p>Instead you can switch to Nicepage full width template in page settings.</p>',
            ),
            array(
                'name' => __('Header and Footer', 'nicepage'),
                'type' => 'heading',
            ),
            array(
                'id'   => 'np_template',
                'name' => __('Default Page Template', 'nicepage'),
                'type' => 'select',
                'options' => array(
                        'html' => 'Nicepage Header and Footer',
                        'html-header-footer' => 'Theme Header and Footer',
                        '' => 'Theme Template',
                ),
                'desc' => '',
            ),
            array(
                'name' => __('Autosave', 'nicepage'),
                'type' => 'heading',
            ),
            array(
                'id'   => 'np_include_auto_save',
                'name' => __('Autosave changes', 'nicepage'),
                'type' => 'checkbox',
                'desc' => '<p>Nicepage will save changes automatically. If enabled, click the Publish button to update the page on the website.</p>',
            ),
        );

        $previous_plugin_info = NpMeta::get('site_settings');
        if ($previous_plugin_info === null && get_option('np_include_auto_save') === false) {
            update_option('np_include_auto_save', '1');
        }

        self::$defaultOptions = array(
            'np_include_jquery' => 0,
            'np_auto_responsive' => 1,
            'np_template' => 'html',
            'np_include_auto_save' => 0
        );
    }

    /**
     * Get Nicepage option value.
     * Returns default value if option is not set.
     *
     * @param string $name
     *
     * @return mixed|false
     */
    public static function getOption($name) {
        $result = get_option($name);
        if ($result === false) {
            $result = _arr(self::$defaultOptions, $name);
        }
        return $result;
    }

    /**
     * Print settings admin-page
     */
    public static function settingsPage() {
        add_action('admin_head', 'NpSettings::adminHeadAction');
        add_action('admin_print_scripts-pages_page_functions', 'NpSettings::printDependentFieldScripts');

    ?>
        <div class="wrap">
            <div id="icon-themes" class="icon32"><br /></div>
            <h2><?php _e('Nicepage Settings', 'nicepage'); ?></h2>
            <?php
            if (isset($_REQUEST['Submit'])) {
                foreach (self::$options as $value) {
                    $id = _arr($value, 'id');
                    $val = stripslashes(_arr($_REQUEST, $id, ''));
                    $type = _arr($value, 'type');
                    switch ($type) {
                    case 'checkbox':
                        $val = $val ? 1 : 0;
                        break;
                    case 'numeric':
                        $val = (int)$val;
                        break;
                    }
                    update_option($id, $val);
                }
                echo '<div id="message" class="updated fade"><p><strong>' . __('Settings saved.', 'nicepage') . '</strong></p></div>' . "\n";
            }
            if (isset($_REQUEST['Reset'])) {
                foreach (self::$options as $value) {
                    delete_option(_arr($value, 'id'));
                }
                echo '<div id="message" class="updated fade"><p><strong>' . __('Settings restored.', 'nicepage') . '</strong></p></div>' . "\n";
            }
            echo '<form method="post" id="np_options_form">' . "\n";
            $in_form_table = false;
            $dependent_fields = array();
            $op_by_id = array();
            $used_when = __('Used when <strong>"%s"</strong> is enabled', 'nicepage');

            foreach (self::$options as $op) {
                $id = _arr($op, 'id');
                $type = _arr($op, 'type');
                $name = _arr($op, 'name');
                $desc = _arr($op, 'desc');
                $script = _arr($op, 'script');
                $depend = _arr($op, 'depend');
                $show = _arr($op, 'show', true);

                if (is_bool($show) && !$show || is_callable($show) && !call_user_func($show)) {
                    continue;
                }

                $op_by_id[$id] = $op;
                if ($depend) {
                    $dependent_fields[] = array($depend, $id);
                    $desc = (!$desc ? '' : $desc . '<br />') . sprintf($used_when, _arr(_arr($op_by_id, $depend), 'name', 'section'));
                }
                if ($type == 'heading') {
                    if ($in_form_table) {
                        echo '</table>' . "\n";
                        $in_form_table = false;
                    }
                    echo '<h3>' . $name . '</h3>' . "\n";
                    if ($desc) {
                        echo "\n" . '<p class="description">' . $desc .  '</p>' . "\n";
                    }
                } else {
                    if (!$in_form_table) {
                        echo '<table class="form-table">' . "\n";
                        $in_form_table = true;
                    }
                    echo '<tr valign="top">' . "\n";
                    echo '<th scope="row">' . $name . '</th>' . "\n";
                    echo '<td>' . "\n";
                    $val = self::getOption($id);
                    self::_printOptionControl($op, $val);
                    if ($desc) {
                        echo '<span class="description">' . $desc . '</span>' . "\n";
                    }
                    if ($script) {
                        echo '<script>' . $script . '</script>' . "\n";
                    }
                    echo '</td>' . "\n";
                    echo '</tr>' . "\n";
                }
            }
            if ($in_form_table) {
                echo '</table>' . "\n";
            }
            echo "<script>\r\n";
            for ($i = 0; $i < count($dependent_fields); $i++) {
                echo "makeDependentField('{$dependent_fields[$i][0]}', '{$dependent_fields[$i][1]}');" . PHP_EOL;
            }
            ?>
            jQuery('#np_options_form').bind('submit', function() {
                jQuery('input, textarea', this).each(function() {
                    jQuery(this).removeAttr('disabled').removeClass('disabled');
                });
            });
            </script>
            <p class="submit">
                <input name="Submit" type="submit" class="button-primary" value="<?php echo esc_attr(__('Save Changes', 'nicepage')) ?>" />
                <input name="Reset" type="submit" class="button-secondary" value="<?php echo esc_attr(__('Reset to Default', 'nicepage')) ?>" />
            </p>
            </form>
            <?php do_action('np_options'); ?>
        </div>
        <?php
    }

    /**
     * Print option control
     *
     * @param string $option
     * @param mixed  $val
     */
    private static function _printOptionControl($option, $val) {
        $id = _arr($option, 'id');
        $type = _arr($option, 'type');

        switch ($type) {
        case "buttonNp":
            echo '<a href="'.admin_url('post-new.php?post_type=page&np_new=1').'" id="start-nicepage" class="button nicepage-editor">Create page</a>' . "\n";
            break;
        case "numeric":
            echo '<input	name="' . $id . '" id="' . $id . '" type="text" value="' . absint($val) . '" class="small-text" />' . "\n";
            break;
        case "select":
            echo '<select name="' . $id . '" id="' . $id . '">' . "\n";
            foreach ($option['options'] as $key => $value) {
                $selected = ($val == $key ? ' selected="selected"' : '');
                echo '<option' . $selected . ' value="' . $key . '">' . esc_html($value) . '</option>' . "\n";
            }
            echo '</select>' . "\n";
            break;
        case "textarea":
            echo '<textarea name="' . $id . '" id="' . $id . '" rows="10" cols="50" class="large-text code">' . esc_html($val) . '</textarea><br />' . "\n";
            break;
        case "radio":
            foreach ($option['options'] as $key => $value) {
                $checked = ( $key == $val ? 'checked="checked"' : '');
                echo '<input type="radio" name="' . $id . '" id="' . $id . '" value="' . esc_attr($key) . '" ' . $checked . '/>' . esc_html($value) . '<br />' . "\n";
            }
            break;
        case "checkbox":
            $checked = ($val ? 'checked="checked" ' : '');
            echo '<input type="checkbox" name="' . $id . '" id="' . $id . '" value="1" ' . $checked . '/>' . "\n";
            break;
        default:
            if ($type == 'text') {
                $class = 'regular-text';
            } else {
                $class = 'large-text';
            }
            echo '<input	name="' . $id . '" id="' . $id . '" type="text" value="' . esc_attr($val) . '" class="' . $class . '" />' . "\n";
            break;
        }
    }

    /**
     * Action on admin_head
     */
    public function adminHeadAction() {
        ?>
        <style>
            #np_options_form .form-table {
                margin-bottom: 30px;
            }
        </style>
        <?php
    }

    /**
     * Action on admin_print_scripts-pages_page_functions
     */
    public function printDependentFieldScripts() {
        ?>
        <script>
            function makeDependentField(master, slave) {
                var $ = jQuery;
                master = $('#' + master);
                slave = $('#' + slave);
                master.bind('click', switchDependentField);
                switchDependentField.call(master);
                function switchDependentField() {
                    if($(this).attr('checked')) {
                        slave.removeAttr('disabled').removeClass('disabled');
                    } else {
                        slave.attr('disabled', 'disabled').addClass('disabled');
                    }
                }
            }
        </script>
        <?php
    }
}

NpSettings::init();