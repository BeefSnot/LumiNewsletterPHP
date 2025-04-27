<?php
// Allow JavaScript content type
header('Content-Type: application/javascript');

// Get site URL from database
require_once '../../includes/db.php';

// Get site URL from settings
$settingsResult = $db->query("SELECT value FROM settings WHERE name = 'site_url'");
if ($settingsResult && $settingsResult->num_rows > 0) {
    $siteUrl = $settingsResult->fetch_assoc()['value'];
} else {
    // Fallback to auto-detected URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
    $siteUrl = $protocol . $host . $path;
}

// Ensure URL doesn't end with a slash
$siteUrl = rtrim($siteUrl, '/');
$widgetUrl = $siteUrl . '/widget.php';
?>
!function(){const e={widgetUrl:"<?php echo $widgetUrl; ?>",height:"320px",width:"100%",maxWidth:"400px"};window.createLumiNewsletterWidget=function(t){const n=document.getElementById(t);if(!n)return;const i=document.createElement("iframe");i.src=e.widgetUrl,i.style.width=n.getAttribute("data-width")||e.width,i.style.maxWidth=n.getAttribute("data-max-width")||e.maxWidth,i.style.height=n.getAttribute("data-height")||e.height,i.style.border="none",i.style.overflow="hidden",i.scrolling="no",i.frameBorder="0",n.appendChild(i),window.addEventListener("message",function(e){if(new URL(i.src).origin!==e.origin)return;e.data&&"resize"===e.data.type&&(i.style.height=e.data.height+"px")})}}();