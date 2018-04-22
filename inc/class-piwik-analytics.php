<?php
class Wpmc_PiwikAnalytics
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_action_save_piwik', array($this, 'save_piwik'));
        add_filter( 'woocommerce_integrations', array($this,'wc_piwik_add_integration' ));
        add_action( 'wp_footer', array( $this, 'spool_analytics' ) );
    }

    public function menu()
    {
        add_menu_page(__('Campaigns.io', 'wpmc'), __('Campaigns.io', 'wpmc'), 'manage_options', 'wpmc-setting', array($this, 'piwik_setting'));
    }

    public function piwik_setting()
    {
        $settings = get_option('wpmc_piwik_setting');

        echo $this->render('piwik_settings', array('settings' => $settings));
    }


    public function wc_piwik_add_integration( $integrations ) {

    	global $woocommerce;

    	if ( is_object( $woocommerce ) && version_compare( $woocommerce->version, '2.1-beta-1', '>=' ) ) {

    		include_once( 'class-wc-piwik.php');
    		$integrations[] = 'Wpmc_woo_Piwik';
    	}

    	return $integrations;
    }



    public function save_piwik()
    {
        $array['piwik_siteid']   = $_POST['piwik_siteid'];
        $array['piwik_hostname'] = $_POST['piwik_hostname'];
        update_option('wpmc_piwik_setting', $array);
        add_action('admin_notices', array($this, 'save_success_message'));
        wp_redirect('admin.php?page=wpmc-setting');

    }

    public function save_success_message()
    {
        ?>
		<div class="notice notice-success is-dismissible">
		       <p><?php _e('Saved!', '');?></p>
		   </div>
<?php
}

    public static function render($view, $data = null)
    {
        // Handle data
        ($data) ? extract($data) : null;

        ob_start();
        include plugin_dir_path(__FILE__) . '../views/' . $view . '.php';
        $view = ob_get_contents();
        ob_end_clean();

        return $view;
    }

    public function spool_analytics() {
			?><?php
			if(!is_admin()) {
			$options  = get_option('wpmc_piwik_setting');
            $options['piwik_hostname'] = ($options['piwik_hostname'])?$options['piwik_hostname']:'stats.campaigns.io';
            $domain_name = $_SERVER['HTTP_HOST'];
			?>

			<script type="text/javascript">
				var _paq = _paq || [];
                _paq.push(["setDomains", ["*.www.<?php echo $domain_name; ?>"]]);
				_paq.push(["trackPageView"]);
				_paq.push(["enableLinkTracking"]);
				(function () {
					var u = (("https:" == document.location.protocol) ? "https" : "http") + "://<?php echo esc_js($options['piwik_hostname']); ?>/";
					_paq.push(["setTrackerUrl", u + "piwik.php"]);
					_paq.push(["setSiteId", <?php echo esc_js($options['piwik_siteid']); ?>]);
					var d = document, g = d.createElement("script"), s = d.getElementsByTagName("script")[0];
					g.type = "text/javascript";
					g.defer = true;
					g.async = true;
					g.src = u + "piwik.js";
					s.parentNode.insertBefore(g, s);
				})();
			</script>
			<noscript>
				<p>
					<img
						src="http://<?php echo esc_js( $options['piwik_hostname'] ); ?>/piwik.php?idsite=<?php echo esc_js($options['piwik_siteid'] ); ?>"
						style="border:0;" alt=""/>
				</p>
			</noscript>
		<?php
					}
			}

}
