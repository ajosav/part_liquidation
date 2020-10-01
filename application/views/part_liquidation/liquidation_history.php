<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<section class="main-content container">


        
      
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default collapsed">
                <div class="panel-heading">
                    Liquidation History
                </div>
                <div class="panel-body table-responsive">
                    <table id="datatable" class="table table-striped dt-responsive nowrap">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Schedule ID</th>
                                <th>Liquidation Amount</th>
                                <th>Principal Balance</th>
                                <th>Outstanding Bal.</th>
                                <th>Payment Status</th>
                                <th>Status</th>
                                <th>Date Generated</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody> 
                        <?php if(count($loan_schedule) > 0): ?>    

                            <?php $sn = 1; foreach($loan_schedule as $schedule): ?> 
                                <tr>
                                    <td><?=$sn++; ?></td>
                                    <td><?= $schedule['schedule_id'] ?></td>
                                    <td><?= number_format($schedule['liquidationAmount'], 2) ?></td>
                                    <td><?= number_format($schedule['principalBalance'], 2) ?></td>
                                    <td><?= number_format($schedule['outstandingBalance'], 2) ?></td>
                                    <td><?= $schedule['paymentStatus']?></td>
                                    <td>
                                        <?php 
                                            switch($schedule['status']) {
                                                case('0') :
                                                    echo "Pending User Confirmation";
                                                break;
                                                case('1'):
                                                    echo "Awaiting Client Approval";
                                                break;
                                                case('2'):
                                                    echo "Effected on loan";
                                                break;
                                                case('3'):
                                                    echo "Rejected By Client";
                                                break;
                                                case('4'):
                                                    echo "OTP Validation";
                                                break;
                                                default:
                                                    echo "Under Processing";

                                            } 
                                        ?> 
                                    </td>
                                    
                                    <td><?= $schedule['date_generated'] ?></td>
                                    <td><a href="<?= base_url() ?>Loan_account/schedule_review/<?= $schedule['schedule_id'] ?>" class="btn btn-info"><i class="fa fa-eye"></i> View Schedule</a></td>
                                </tr>
                            <?php endforeach ?> 
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div><!--end row-->
			
			