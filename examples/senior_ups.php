<h3 class="widgettitle">Senior UPS</h3>
<?php

	require_once 'class.CranleighSNMPCheck.php';
	

	$check = new CranleighSNMPCheck("192.168.1.1", "myupscommunity");
	echo $check->displayBlock();

// Ensure that the IP Address above is the IP address of your connected UPS
