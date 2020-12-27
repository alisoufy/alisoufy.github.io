<?php
defined('ABSPATH') or die;
/**
 * Page template with raw html
 */

global $post;
$data_provider = np_data_provider($post->ID);
$headerNp = $data_provider->getNpHeader();
$footerNp = $data_provider->getNpFooter();
$tmpPath = get_template_directory();
$cookiesSection = '';
$cookiesConsent = NpMeta::get('cookiesConsent') ? json_decode(NpMeta::get('cookiesConsent'), true) : '';
if ($cookiesConsent && (!$cookiesConsent['hideCookies'] || $cookiesConsent['hideCookies'] === 'false')) {
    $cookiesSection = $cookiesConsent['publishCookiesSection'];
    $cookiesSection = $data_provider->fixImagePaths($cookiesSection);
}
ob_start();
?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php wp_head(); ?>
    </head>
    <body class="<?php echo $data_provider->getPageBodyClass(); ?>"
          style="<?php echo $data_provider->getPageBodyStyle(); ?>">
    <?php $headerItem = '';
    if ($headerNp && !$data_provider->getHideHeader()) {
        $headerItem = json_decode($headerNp, true);
        $publishHeader = $headerItem['php'];
        $publishHeader = Nicepage::processFormCustomPhp($publishHeader, 'header');
        $publishHeader = Nicepage::processContent($publishHeader, true, 'header');
    }
    if ($headerItem) {
        echo $headerItem['styles'];
        echo $publishHeader;
    }
    the_post();
    the_content();
    $footerItem = '';
    if ($footerNp && !$data_provider->getHideFooter()) {
        $footerItem = json_decode($footerNp, true);
        $publishFooter = $footerItem['php'];
        $publishFooter = Nicepage::processFormCustomPhp($publishFooter, 'footer');
        $publishFooter = Nicepage::processContent($publishFooter, true, 'footer');
    }
    if ($footerItem) {
        echo $footerItem['styles'];
        echo $publishFooter;
    }
    wp_footer(); ?>
    <?php echo $cookiesSection; ?>
    </body>
    </html>
<?php
$htmlDocument = ob_get_clean();
$htmlDocument = $data_provider->addPublishDialogToBody($htmlDocument, $headerItem, $footerItem);
echo $htmlDocument;