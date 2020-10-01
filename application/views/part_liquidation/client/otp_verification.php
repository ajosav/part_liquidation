
<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="main-content container">

    <div class="row">

        <div class="container-fluid">
            <div class="col-lg-6 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">OTP Validation</div>
                        <div class="panel-body text-center">
                            <div class="alert alert-succes" style="display: none;" id="otp_resend_success">
                            </div>
                            <form method="post" action="<?=base_url()?>Client/otp_validation" id="otp_validation">
                                <div class="form-row">
                                     
                                    <input type="hidden" name="client_fname" value="<?= $client_fname ?>">
                                    <input type="hidden" name="client_lname" value="<?= $client_lname ?>">
                                    <input type="hidden" name="client_email" value="<?= $client_email ?>">
                                    <input type="hidden" name="schedule_id" id="schedule_id" value="<?= $schedule_id?>">
                                    <input type="hidden"  placeholder="Loan ID" name="loan_id" class="form-control" value="<?= $loan_id ?>" title="Client Loan ID">
                                    <div class="fom-group">
                                        <div class="form-heading">Confirm the OTP sent to your phone <?=$phone ?> and email </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <input type="text" id="otp" name="otp" class="form-control"placeholder="OTP">
                                        <span class="help-block" id="otp_error" style="color: red;"></span>
                                    </div>
                                    
                                    <button class="btn btn-success col-lg-12" id="confirm_otp">Confirm OTP</button>
                                </div>
                                <a href="" id="resend_otp"><i class="fa fa-refresh"></i> Resend OTP</a>

                            </form> 
                        </div>
                    </div> 
                    <!-- END panel-->
                </div>
            </div>
            
        </div>

    </div>
</section>