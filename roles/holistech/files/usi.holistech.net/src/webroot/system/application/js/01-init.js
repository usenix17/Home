	$J = jQuery.noConflict();
jQuery.extend(jQuery.expr[':'], {
  containsCI: "(a.textContent||a.innerText||jQuery(a).text()||'').toLowerCase().indexOf((m[3]||'').toLowerCase())>=0"
});

images = [ 'images/Background.png', 'images/Banner.png', 'images/all.png', 'images/alV.png', 'images/allH.png', 'images/ajax-loader.gif', 'images/Bottom.png' ];

$J.each(images, function(i,img)
{
	pre = new Image();
	pre.src = BASEURL+'layout/'+img;
});
