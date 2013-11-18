<?php
//chdir(dirname(__DIR__));

// Setup autoloading
//require 'init_autoloader.php';

// Path to your private key.  Be very careful that this file is not accessible
// from the web!

$private_key_filename = 'key/pk-APKAJC22BYF2JGZTOC6A.pem';
$key_pair_id = 'APKAJC22BYF2JGZTOC6A';
$expires = time() + 300; // 5 min from now
?>

<html>
<head>
<title>CloudFront Streaming and Downloads with signed URLs</title>
<script type="text/javascript" src="jwplayer/jwplayer.js"></script>
</head>
<body>
<h1>Amazon CloudFront Streaming and Downloads with signed URLs</h1>
<h2>Canned Policy</h2>
<h3>Expires at <?= gmdate('Y-m-d H:i:s T', $expires) ?></h3>
<br>
 
 

<?php
//Test Image URL
$image_path = 'http://d1ckv7o9k6o3x9.cloudfront.net/5ec827c4-4301-11e3-85d4-22000a8a1935/images/13833158912013-10-15+21.14.58.jpg';
//$canned_policy_stream_name = get_canned_policy_stream_name($image_path, $private_key_filename, $key_pair_id, $expires);
//Debug
echo '<p><img src="' . $image_path . '"width="200" height="150"/></p>';

$video_download_path = 'http://d1ckv7o9k6o3x9.cloudfront.net/fb4a101a-4308-11e3-85d4-22000a8a1935/media/video3.mp4';
//$video_download_path = 'http://d1ckv7o9k6o3x9.cloudfront.net/VR_MOVIE.mp4';
//$canned_video_download_stream_name = get_canned_policy_stream_name($video_download_path, $private_key_filename, $key_pair_id, $expires);

?>
<p><a href="<?= $video_download_path ?>">This link let's you download and watch the file.</a></p>

<?php 
$video_path = '481f0560-e83e-11e2-8fd6-12313909a953/media/web/VID_20130629_215321.mp4';
// - removed //$video_path = 'VR_MOVIE.mp4';
//$canned_policy_stream_name = get_canned_policy_stream_name($video_path, $private_key_filename, $key_pair_id, $expires);
?>
<!-- Note download links without security don't care if you add a signed URL --> 
<!-- This code is good with signed URLs -->
<div id='player_1'></div>
<script type='text/javascript'>
  jwplayer('player_1').setup({
    //Use this URL to access with security
    //file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:2012-05-26_12-17-55_73.mp4",
    //file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:00686bda-4838-11e3-85d4-22000a8a1935/media/VID_20130629_215321.mp4",
    //file: "rtmp://cp67126.edgefcs.net/ondemand/mediapm/osmf/content/test/akamai_10_year_f8_512K",
    //file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:00686bda-4838-11e3-85d4-22000a8a1935/media/VID_20130629_215321.mp4",
    file: "rtmp://s1iq2cbtoqqky.cloudfront.net/cfx/st/mp4:4fa75452-4c18-11e3-85d4-22000a8a1935/media/1080p/video2.mp4",
    //file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:fb4a101a-4308-11e3-85d4-22000a8a1935/media/video3.mp4",
    width: "480",
    height: "270"
  });
</script>
&nbsp;

<BR><BR><BR>

</body>
</html>
