
<!--Start footer-->
          
            <!--end footer-->

        </section>
        <!--end main content-->
		
		
    <!--Common plugins-->
    
        <script src="<?= base_url() ?>assets_dashboard/plugins/jquery/dist/jquery.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/bootstrap/js/bootstrap.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/pace/pace.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/jasny-bootstrap/js/jasny-bootstrap.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/slimscroll/jquery.slimscroll.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/nano-scroll/jquery.nanoscroller.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/metisMenu/metisMenu.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/js/float-custom.js"></script>
        <!--page script-->
        <script src="<?= base_url() ?>assets_dashboard/plugins/chart-c3/d3.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/chart-c3/c3.min.js"></script>
        <!-- iCheck for radio and checkboxes -->
        <script src="<?= base_url() ?>assets_dashboard/plugins/iCheck/icheck.min.js"></script>
        <!-- Datatables-->
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/dataTables.buttons.min.js"></script> 
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/buttons.flash.min.js"></script>
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/buttons.html5.min.js"></script>	
       <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/buttons.print.min.js"></script> 
	   <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/vfs_fonts.js"></script> 
	    <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/pdfmake.min.js"></script> 
	   <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/jszip.min.js"></script> 
	    <script src="<?php echo base_url() ?>assets_dashboard/plugins/datatables/buttons.dataTables.min.js"></script> 
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/dataTables.tableTools.min.css"></script> 
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/dataTables.tableTools.js"></script>
			<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/dataTables.tableTools.min.js"></script>
		<script src="<?= base_url() ?>assets_dashboard/plugins/datatables/xls.png "></script>
	   
	   <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/datatables/dataTables.responsive.min.js"></script>
        <script src="<?= base_url() ?>assets_dashboard/plugins/toast/jquery.toast.min.js"></script>
        <script src="<?//= base_url() ?>assets_dashboard/js/dashboard-alpha.js"></script>
		 <script src="<?= base_url() ?>assets_dashboard/js/e-commerce-dashboard-custom.js"></script> 
    

	
	 <script>
            $(document).ready(function () {
                $('.i-checks').iCheck({
                    checkboxClass: 'icheckbox_square-blue',
                    radioClass: 'iradio_square-blue'
                });
            });
			
			

            //sparkline
            $("#sparkline4").sparkline([34, 43, 43, 35, 44, 32, 15, 22, 46, 33, 86, 54, 73, 53, 12, 53, 23, 65, 23, 63, 53, 42, 34, 56, 76, 15, 54, 23, 44], {
                type: 'line',
                lineWidth: "2",
                lineColor: '#fff',
                fillColor: '#23b7e5',
                height: "80",
                width: "100%"
            });
            $("#sparkline5").sparkline([43, 56, 34, 76, 54, 34, 21, 25, 46, 33, 86, 54, 73, 53, 12, 53, 23, 65, 23, 63, 53, 65, 43, 56, 46, 15, 54, 23, 44], {
                type: 'line',
                lineWidth: "2",
                lineColor: '#fff',
                fillColor: '#7986CB',
                height: "80",
                width: "100%"
            });
            //flot chart
            var d1 = [[1262304000000, 6], [1264982400000, 3057], [1267401600000, 20434], [1270080000000, 31982], [1272672000000, 26602], [1275350400000, 27826], [1277942400000, 24302], [1280620800000, 24237], [1283299200000, 21004], [1285891200000, 12144], [1288569600000, 10577], [1291161600000, 10295]];
            var d2 = [[1262304000000, 5], [1264982400000, 200], [1267401600000, 1605], [1270080000000, 6129], [1272672000000, 11643], [1275350400000, 19055], [1277942400000, 30062], [1280620800000, 39197], [1283299200000, 37000], [1285891200000, 27000], [1288569600000, 21000], [1291161600000, 17000]];

            var data1 = [
                {label: "Data 1", data: d1, color: '#52cff6'},
                {label: "Data 2", data: d2, color: '#1390b7'}
            ];
            $.plot($("#flot-chart1"), data1, {
                xaxis: {
                    tickDecimals: 0
                },
                series: {
                    lines: {
                        show: true,
                        fill: true,
                        fillColor: {
                            colors: [{
                                    opacity: 1
                                }, {
                                    opacity: 1
                                }]
                        }
                    },
                    points: {
                        width: 0.1,
                        show: false
                    }
                },
                grid: {
                    show: false,
                    borderWidth: 0
                },
                legend: {
                    show: false
                }
            });
			

					
    
		
      
	
		</script>
		
			
		<script>
		 $(document).ready(function () {
                $('#datatable').dataTable();
            });
		</script>
		<script>
		 $(document).ready(function () {
                $('#data1').dataTable();
            });
		</script>
		<script>
		 $(document).ready(function () {
                $('#table3').dataTable();
            });
		</script>
		


	
 <script>
	

    $('document').ready(function() {
        var base_url = "<?= base_url() ?>";
        $('form#recalculate').submit(function(e) {
            e.preventDefault();

            var amountError = $('#liquidation_amount');
            var tenorError = $('#payment_tenor');
            var amount = $('input[name="part_liq_amount"]').val();
            var repayment_tenor = $('input[name="repayment_tenor"]').val();
            var max_tenor = $('input[name="max_tenor"]').val();

            tenorError.html('');
            amountError.html('');
            if(amount == "") {
                amountError.html('Liquidation amount is required to continue')
                return false;
            }
            if(repayment_tenor == '')  {
                tenorError.html('Loan tenor is required');
                return false;
            }

            if(Number(repayment_tenor) > Number(max_tenor )) {
                tenorError.html('Exceeds Maximum tenor');
                return false;
            }
            var form = $('form#recalculate').serialize();

            $('.btn').button('loading')
            $.ajax({
                url: base_url+"Loan_account/recalculate_schedule",
                type: "post",
                headers: {
                    "accept": "application/json",
                    "Access-Control-Allow-Origin":"*"
                },
                data: form,
                crossDomain: true,
                success: function(data) {
                    console.log(data)
                    if(data.status == 'created') {

                        alert(data.message);
                    }
                    location.href=`${base_url}Loan_account/schedule_review/${data.schedule_id}`;
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                if(XMLHttpRequest.status == 200) {
                    console.log(XMLHttpRequest)
                    alert(XMLHttpRequest.responseText);
                    // window.location.reload();
                } else {
                    console.log("Error:", XMLHttpRequest)
                    alert(XMLHttpRequest.responseText);
                    $('.btn').button('reset');
                }
                }
            }).done(function() {
                $('.btn').button('reset');
            });
        });

        $('button#reject_liquidation').click(function(e) {
            e.preventDefault();
            $('#exampleModalCenter').modal('show');
        });
        $('button#submit_rejection').click(function(e) {
            $('.btn').button('loading')
            if($('#rejection_reason').val() == '') {
                alert('Please enter the reason for rejection');
                $('.btn').button('reset');
                return;
            }
            e.preventDefault();
            var schedule_id = $(this).attr('data-id');

            var form = $('form#rejection_form').serialize() + `&schedule_id=${schedule_id}`;
            $.ajax({
            url: base_url+"client/reject_schedule",
            data: form,
            type: "POST",
            success: function(data) {
                alert("Liquidation Schedule Successfully Declined ");
                window.location.replace("https://www.renmoney.com")
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                if(XMLHttpRequest.status == 200) {
                    alert(XMLHttpRequest.responseText);
                    window.location.replace("https://www.renmoney.com/");
                } else {
                    alert(XMLHttpRequest.responseText);
                    $('.btn').button('reset');
                }
            }
        }).done(function() {
        $('.btn').button('reset');
        });

       

         });


        $("input[name='tenure_type']").change(function() {
            if($(this).val() == 'new') {
                $("input[name='repayment_tenor']").prop('disabled', false)
            } else {
                $("input[name='repayment_tenor']").prop('disabled', true)
            }
        })
        $("input[name='tenure_type']").each(function() {
            if(this.checked == 'new') {
                $("input[name='repayment_tenor']").prop('disabled', false)
            } else {
                $("input[name='repayment_tenor']").prop('disabled', true)
            }
        });

        $("form#otp_validation").submit(function(e) {
            e.preventDefault();
            var form = $('form#otp_validation').serialize();
            $otp_error = $('#otp_error');
            $('.btn').button('loading')
            $.ajax({
                url: base_url+"client/otp_validation",
                type: "post",
                headers: {
                    "accept": "application/json",
                    "Access-Control-Allow-Origin":"*"
                },
                data: form,
                crossDomain: true,
                success: function(data) {
                    if(data.status == 'created') {
                        alert(data.message);
                    }
                    // location.href=`${base_url}Loan_account/schedule_review/${data.schedule_id}`;
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    if(XMLHttpRequest.status == 200) {
                        console.log(XMLHttpRequest)
                        alert(XMLHttpRequest.responseText);
                        // window.location.reload();
                    } else {
                    otp_error.html(XMLHttpRequest.responseText);
                        $('.btn').button('reset');
                    }
                }
            }).done(function() {
                $('.btn').button('reset');
            });

            
        })
        $('#resend_otp').click(function(e) {
            e.preventDefault();
            var form = $('form#otp_validation').serialize();
            otp_error = $('#otp_error');
            otp_success = $('#otp_resend_success');
            
            otp_success.html('')
            otp_error.html('')

            $('.btn').button('loading')
            $.ajax({
                url: base_url+"client/resend_otp",
                type: "post",
                headers: {
                    "accept": "application/json",
                    "Access-Control-Allow-Origin":"*"
                },
                data: form,
                crossDomain: true,
                success: function(data) {
                   otp_success.html(data)
                    // location.href=`${base_url}Loan_account/schedule_review/${data.schedule_id}`;
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    if(XMLHttpRequest.status == 200) {  
                        otp_success.html(XMLHttpRequest.responseText);
                        $('#otp_resend_success').css('display', 'block')
                        // window.location.reload();
                    } else {
                        otp_error.html(XMLHttpRequest.responseText);
                        $('.btn').button('reset');
                    }
                }
            }).done(function() {
                $('.btn').button('reset');
            });
                
        })
    });
</script>
<!-- Mirrored from bootstraplovers.com/templates/float-admin-v1.1/light-version/index.html by HTTrack Website Copier/3.x [XR&CO'2014], Tue, 04 Apr 2017 15:23:24 GMT -->
</body>
</html>