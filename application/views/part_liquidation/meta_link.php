<?php defined('BASEPATH') OR exit('No direct script access allowed');  ?>
<!DOCTYPE html>
<html lang="en">
    
<head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
        <title>RenMony | Integration Broker</title>
		 <!-- Renmoney shortcut icons -->
		<link rel="profile" href="http://gmpg.org/xfn/11" />
		<link rel="pingback" href="https://www.renmoneyng.com/xmlrpc.php" />
		<link rel="shortcut icon" type="image/x-icon" href="http://renmoney.wpengine.com/wp-content/uploads/2014/09/logo_icon.png">
		<link rel="apple-touch-icon" href="http://renmoney.wpengine.com/wp-content/uploads/2014/09/logo_icon.png"/>
	
        <!-- Common plugins -->
        <link href="<?= base_url() ?>assets_dashboard/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets_dashboard/plugins/simple-line-icons/simple-line-icons.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets_dashboard/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets_dashboard/plugins/pace/pace.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets_dashboard/plugins/jasny-bootstrap/css/jasny-bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= base_url() ?>assets_dashboard/plugins/nano-scroll/nanoscroller.css">
        <link rel="stylesheet" href="<?= base_url() ?>assets_dashboard/plugins/metisMenu/metisMenu.min.css">
        <link href="<?= base_url() ?>assets_dashboard/plugins/chart-c3/c3.min.css" rel="stylesheet">
        <link href="<?= base_url() ?>assets_dashboard/plugins/iCheck/blue.css" rel="stylesheet">
        <!-- dataTables -->
        <link href="<?= base_url() ?>assets_dashboard/plugins/datatables/jquery.dataTables.min.css" rel="stylesheet" type="text/css">
        <link href="<?= base_url() ?>assets_dashboard/plugins/datatables/responsive.bootstrap.min.css" rel="stylesheet" type="text/css">
        <link href="<?= base_url() ?>assets_dashboard/plugins/toast/jquery.toast.min.css" rel="stylesheet">
        <!--template css-->
        <link href="<?= base_url() ?>assets_dashboard/css/style.css" rel="stylesheet">
        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
		
		

		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css">
			
		<!-- dev URL rawgit.com--> 
		<script src="https://cdn.rawgit.com/unconditional/jquery-table2excel/master/src/jquery.table2excel.js"></script>
	<!--	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>--> 

	<!-- !!! use this URL for production:
		<script src="https://cdn.rawgit.com/unconditional/jquery-table2excel/master/src/jquery.table2excel.js"></script>
		-->
		
		
		
	<!-- 	<script src="http://ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.jss"></script> -->
		<script src="<?= base_url() ?>src/jquery.table2excel.js"></script>
	
    </head>
    <body>
	 <!--top bar start-->
        <div class="top-bar light-top-bar"><!--by default top bar is dark, add .light-top-bar class to make it light-->
            <div class="container-fluid">
                <div class="row">
				
                    <div class="col-xs-6">
			<!-- login user session -->
               
                        <a href="#" class="admin-logo">
                             <?php if(strpos($_SERVER["REQUEST_URI"], "Client/")):?>
              
                                <img src="<?= base_url() ?>assets_dashboard/images/renmoney-log22.png" >
                         
                            <?php endif ?>
                          
                        </a>
				
                        <div class="left-nav-toggle visible-xs visible-sm">
                            <a href="#">
                                <i class="glyphicon glyphicon-menu-hamburger"></i>
                            </a>
                        </div><!--end nav toggle icon-->
                        <!--start search form-->
                       <!-- <div class="search-form hidden-xs">
                            <form>
                                <input type="text" class="form-control" placeholder="Search for...">
                                <button type="button" class="btn-search"><i class="fa fa-search"></i></button>
                            </form>
                        </div>-->
                        <!--end search form-->
                    </div>
					
				
                    <div class="col-xs-6">
                        <ul class="list-inline top-right-nav">
                     
							
						 
							
                           
							 </li> 
 </li> 
                        </ul> 
                    </div>
                </div>
            </div>
        </div>
        <!-- top bar end-->