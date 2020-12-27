<?php
defined('ABSPATH') or die;

if (isset($controlProps) && isset($controlTemplate)) {
    $redirect = '';
    if (!is_user_logged_in()) {
        $loginText = __('Log in', 'nicepage');
        $loginLink = esc_url(wp_login_url($redirect));
    } else {
        $loginText = __('Log out', 'nicepage');
        $loginLink = esc_url(wp_logout_url($redirect));
    }
    $userUrl = $controlProps['href'];
    if ($userUrl === '' || $userUrl === '#') {
        $userUrl = $loginLink;
    }
    $controlTemplate = str_replace('[[content]]', $loginText, $controlTemplate);
    $controlTemplate = str_replace('[[href]]', $userUrl, $controlTemplate);
    echo $controlTemplate;
}
