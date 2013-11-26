<?php

/**
 * Name: WordPress Post Connector
 * Description: Post to WordPress (or anything else which uses the wordpress XMLRPC API)
 * Version: 1.0
 * Author: Mike Macgirvin <http://macgirvin.com/profile/mike>
 */

require_once('include/permissions.php');
require_once('library/IXR_Library.php');


function wppost_load () {
    register_hook('post_local',              'addon/wppost/wppost.php', 'wppost_post_local');
    register_hook('notifier_normal',         'addon/wppost/wppost.php', 'wppost_send');
    register_hook('jot_networks',            'addon/wppost/wppost.php', 'wppost_jot_nets');
    register_hook('feature_settings',        'addon/wppost/wppost.php', 'wppost_settings');
    register_hook('feature_settings_post',   'addon/wppost/wppost.php', 'wppost_settings_post');
    register_hook('drop_item',               'addon/wppost/wppost.php', 'wppost_drop_item');	
}

function wppost_unload () {
    unregister_hook('post_local',            'addon/wppost/wppost.php', 'wppost_post_local');
    unregister_hook('notifier_normal',       'addon/wppost/wppost.php', 'wppost_send');
    unregister_hook('jot_networks',          'addon/wppost/wppost.php', 'wppost_jot_nets');
    unregister_hook('feature_settings',      'addon/wppost/wppost.php', 'wppost_settings');
    unregister_hook('feature_settings_post', 'addon/wppost/wppost.php', 'wppost_settings_post');
    unregister_hook('drop_item',             'addon/wppost/wppost.php', 'wppost_drop_item');
}


function wppost_jot_nets(&$a,&$b) {
    if((! local_user()) || (! perm_is_allowed(local_user(),'','view_stream')))
        return;

    $wp_post = get_pconfig(local_user(),'wppost','post');
    if(intval($wp_post) == 1) {
        $wp_defpost = get_pconfig(local_user(),'wppost','post_by_default');
        $selected = ((intval($wp_defpost) == 1) ? ' checked="checked" ' : '');
        $b .= '<div class="profile-jot-net"><input type="checkbox" name="wppost_enable" ' . $selected . ' value="1" /> '
            . t('Post to Wordpress') . '</div>';
    }
}


function wppost_settings(&$a,&$s) {

    if(! local_user())
        return;

    /* Add our stylesheet to the page so we can make our settings look nice */

	head_add_css('/addon/wppost/wppost.css');

    /* Get the current state of our config variables */

    $enabled = get_pconfig(local_user(),'wppost','post');

    $checked = (($enabled) ? ' checked="checked" ' : '');

    $def_enabled = get_pconfig(local_user(),'wppost','post_by_default');

    $def_checked = (($def_enabled) ? ' checked="checked" ' : '');

	$wp_username = get_pconfig(local_user(), 'wppost', 'wp_username');
	$wp_password = get_pconfig(local_user(), 'wppost', 'wp_password');
	$wp_blog = get_pconfig(local_user(), 'wppost', 'wp_blog');


    /* Add some HTML to the existing form */

    $s .= '<div class="settings-block">';
    $s .= '<h3>' . t('WordPress Post Settings') . '</h3>';
    $s .= '<div id="wppost-enable-wrapper">';
    $s .= '<label id="wppost-enable-label" for="wppost-checkbox">' . t('Enable WordPress Post Plugin') . '</label>';
    $s .= '<input id="wppost-checkbox" type="checkbox" name="wppost" value="1" ' . $checked . '/>';
    $s .= '</div><div class="clear"></div>';

    $s .= '<div id="wppost-username-wrapper">';
    $s .= '<label id="wppost-username-label" for="wppost-username">' . t('WordPress username') . '</label>';
    $s .= '<input id="wppost-username" type="text" name="wp_username" value="' . $wp_username . '" />';
    $s .= '</div><div class="clear"></div>';

    $s .= '<div id="wppost-password-wrapper">';
    $s .= '<label id="wppost-password-label" for="wppost-password">' . t('WordPress password') . '</label>';
    $s .= '<input id="wppost-password" type="password" name="wp_password" value="' . $wp_password . '" />';
    $s .= '</div><div class="clear"></div>';

    $s .= '<div id="wppost-blog-wrapper">';
    $s .= '<label id="wppost-blog-label" for="wppost-blog">' . t('WordPress API URL') . '</label>';
    $s .= '<input id="wppost-blog" type="text" name="wp_blog" value="' . $wp_blog . '" />';
    $s .= '</div><div class="clear"></div>';

    $s .= '<div id="wppost-bydefault-wrapper">';
    $s .= '<label id="wppost-bydefault-label" for="wppost-bydefault">' . t('Post to WordPress by default') . '</label>';
    $s .= '<input id="wppost-bydefault" type="checkbox" name="wp_bydefault" value="1" ' . $def_checked . '/>';
    $s .= '</div><div class="clear"></div>';

    /* provide a submit button */

    $s .= '<div class="settings-submit-wrapper" ><input type="submit" id="wppost-submit" name="wppost-submit" class="settings-submit" value="' . t('Submit') . '" /></div></div>';

}


function wppost_settings_post(&$a,&$b) {
	if(x($_POST,'wppost-submit')) {
		set_pconfig(local_user(),'wppost','post',intval($_POST['wppost']));
		set_pconfig(local_user(),'wppost','post_by_default',intval($_POST['wp_bydefault']));
		set_pconfig(local_user(),'wppost','wp_username',trim($_POST['wp_username']));
		set_pconfig(local_user(),'wppost','wp_password',trim($_POST['wp_password']));
		set_pconfig(local_user(),'wppost','wp_blog',trim($_POST['wp_blog']));
		info( t('Wordpress Settings saved.') . EOL);
	}
}

function wppost_post_local(&$a,&$b) {

	// This can probably be changed to allow editing by pointing to a different API endpoint

	if($b['edit'])
		return;

	if((! local_user()) || (local_user() != $b['uid']))
		return;

	if($b['item_private'] || $b['parent'])
		return;

    $wp_post   = intval(get_pconfig(local_user(),'wppost','post'));

	$wp_enable = (($wp_post && x($_REQUEST,'wppost_enable')) ? intval($_REQUEST['wppost_enable']) : 0);

	if($_REQUEST['api_source'] && intval(get_pconfig(local_user(),'wppost','post_by_default')))
		$wp_enable = 1;

    if(! $wp_enable)
       return;

    if(strlen($b['postopts']))
       $b['postopts'] .= ',';
     $b['postopts'] .= 'wppost';
}




function wppost_send(&$a,&$b) {

    if($b['item_restrict'] || $b['item_private'] || ($b['created'] !== $b['edited']))
        return;

	if(! perm_is_allowed($b['uid'],'','view_stream'))
		return;

    if(! strstr($b['postopts'],'wppost'))
        return;

    if($b['parent'] != $b['id'])
        return;
	logger('Wordpress xpost invoked', LOGGER_DEBUG);

	$wp_username = get_pconfig($b['uid'],'wppost','wp_username');
	$wp_password = get_pconfig($b['uid'],'wppost','wp_password');
	$wp_blog     = get_pconfig($b['uid'],'wppost','wp_blog');

	if($wp_username && $wp_password && $wp_blog) {

		require_once('include/bbcode.php');

		$data = array(
			'post_title'   => trim($b['title']),
			'post_content' => bbcode($b['body']),
			'post_type'    => 'post',
			'post_status'  => 'publish'
		);

		$client = new IXR_Client($wp_blog);

		$res = $client->query('wp.newPost',1,$wp_username,$wp_password,$data);

		if(! $res) {
			logger('wppost: failed.');
			return;
		}

		$post_id = $client->getResponse();

		logger('wppost: returns post_id: ' . $post_id, LOGGER_DEBUG);

		if($post_id) {
			q("insert into item_id ( iid, uid, sid, service ) values ( %d, %d, '%s','%s' )",
				intval($b['id']),
				intval($b['uid']),
				dbesc(dirname($wp_blog) . '/' . $post_id),
				dbesc('wordpress')
			);
		}
	}
}


function wppost_drop_item(&$a,&$b) {

    $wp_enabled = get_pconfig($b['item']['uid'],'wppost','post');
	if(! $wp_enabled)
		return;

	$r = q("select * from item_id where service = 'wordpress' and iid = %d and uid = %d limit 1",
		intval($b['item']['id']),
		intval($b['item']['uid'])
	);
	if(! $r)
		return;

	$post_id = basename($r[0]['sid']);

	$wp_username = get_pconfig($b['item']['uid'],'wppost','wp_username');
	$wp_password = get_pconfig($b['item']['uid'],'wppost','wp_password');
	$wp_blog     = get_pconfig($b['item']['uid'],'wppost','wp_blog');

	if($post_id && $wp_username && $wp_password && $wp_blog) {

		$client = new IXR_Client($wp_blog);

		$res = $client->query('wp.deletePost',1,$wp_username,$wp_password,$post_id);

		if(! $res) {
			logger('wppost: delete failed.');
			return;
		}

		$result = intval($client->getResponse());

		logger('wppost: delete post returns: ' . $result, LOGGER_DEBUG);
	
	}

}