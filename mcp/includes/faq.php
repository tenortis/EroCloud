<?php
 
if (!defined('SAFE_INC'))
    die ("Hacking attempt...");
    

$site .= '
<script type="text/javascript">
// <![CDATA[
    jQuery(document).ready(function() {

    })
// ]]>
</script>

<div id="site_faq">
    <h1 class="h4">FAQ - Fragen, Antworten und interessante Tipps</h1>
    
    <div class="tip">
        <span>&bull;</span> <a class="movie_tips" href="javascript:;">Allgemeine Hinweise zur Qualit&auml;t deiner Filme.</a>
    </div>

    <div class="tip">
        <span>&bull;</span> <a class="photo_album_tips" href="javascript:;">Hinweise zur Qualit&auml;t deiner Fotoalben</a>
    </div>

    
    <div class="tip">
        <span>&bull;</span> <a class="movie_rendering_tips" href="javascript:;">So renderst du deine Filme richtig.</a>
    </div>


    <div class="blogs">
        <h2>Beitr&auml;ge in unsern Blog</h2>    
    </div>
    
    <ul style="list-style-type:disc; margin-left:40px; line-height:1.4">
        <li style="padding-bottom:10px;"><b>eigene 09005-Rufnummer</b><br /><a href="https://pay4coins.net/2018/07/26/erocloud-wie-beantrage-ich-eine-eigene-09005-rufnummer/" target="_blank">Wie beantrage ich eine eigene 09005-Rufnummer?</a></li>
        <li style="padding-bottom:10px;"><b>Darsteller importieren</b><br /><a href="https://pay4coins.net/2018/07/11/erocloud-wie-importiere-ich-darsteller-auf-meine-webseite/" target="_blank">Wie importiere ich Darsteller auf meine EroCMS-Webseite?</a></li>
        <li style="padding-bottom:10px;"><b>als Webmaster Geld verdienen</b><br /><a href="https://pay4coins.net/2018/07/08/erocloud-wie-kann-ich-als-webmaster-geld-verdienen/" target="_blank">Wie kann ich als Webmaster Geld verdienen?</a></li>
    </ul>

</div>


<div class="album_tips_popup">
    <div style="text-align:right; top:20px; right:10px; position:absolute;">
        <a href="#" class="close_overlay"><b>&#x2715;</b></a>
    </div>';        

    include_once(MCP_DIR.'/includes/overlays/album_tips.php');

    $site .= '
    <div style="text-align:right; float:right;">
        <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
    </div>
</div>

<div class="movie_tips_popup">
    <div style="text-align:right; top:20px; right:10px; position:absolute;">
        <a href="#" class="close_overlay"><b>&#x2715;</b></a>
    </div>';        
    include_once(MCP_DIR.'/includes/overlays/movie_tips.php');
    $site .= '
    <div style="text-align:right; float:right;">
        <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
    </div>
</div>


<div class="movie_rendering_tips_popup">
    <div style="text-align:right; top:20px; right:10px; position:absolute;">
        <a href="#" class="close_overlay"><b>&#x2715;</b></a>
    </div>';        
    include_once(MCP_DIR.'/includes/overlays/movie_rendering_tips.php');
    $site .= '
    <div style="text-align:right; float:right;">
        <a href="#" class="close_overlay"><b>&#x2715;</b> Schlie&szlig;en</a>
    </div>
</div>



';

?>