<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

        <section class="main-content container">
    
            <!--page header end-->
				<div class="col-lg-12">
                    <!-- START panel-->
                    <div class="panel panel-default">
                        <div class="panel-heading">Repayment Schedule</div>
                            <div class="panel-body">
                                <form class="form-horizontal" method="post" action="<?= base_url() ?>Loan_account/send_schedule_to_customer" style="padding: 10px 40px;">
                    
                                    <div class="row">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <div class="col-md-4">
                                                    <label for="data_file">Loan ID</label>
                                                    <input type="hidden" name="accountHolderKey" id="accountHolderKey" value="<?= $loan_Schedule->accountHolderKey?>">
                                                    <input type="hidden" name="schedule_id" id="schedule_id" value="<?= $loan_Schedule->schedule_id?>">
                                                    <input type="email"  placeholder="Loan ID" name="loan_id" class="form-control" value="<?= $loan_Schedule->loan_id ?>" title="Client Loan ID" readonly>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="date_generated">Liquidation Amount</label>
                                                    <input type="text"  placeholder="Liquidation Amount" name="liquidationAmount" class="form-control" value="<?= $loan_Schedule->liquidationAmount ?>" title="Liquidation Amount" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Principal Balance (After Liquidation)</label>
                                                    <input type="text"  placeholder="Interest Rate" name="principal_balance" class="form-control" value="<?= $loan_Schedule->principalBalance ?>" title="Principal Balance" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Loan Tenure</label>
                                                    <input type="text"  placeholder="Interest Accrued" name="interest_accrued" class="form-control" value="<?= $loan_Schedule->tenure ?>" title="Interest Accrued" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Status</label>
                                                    <input type="text"  placeholder="Intrest Overdue" name="interest_overdue" class="form-control" value="
                                                        <?php
                                                            switch($loan_Schedule->status) {
                                                                    case('0') :
                                                                        echo "Pending User Confirmation";
                                                                    break;
                                                                    case('1'):
                                                                        echo "Awaiting Client Approval";
                                                                    break;
                                                                    case('2'):
                                                                        echo "Applied on loan";
                                                                    break;
                                                                    case('3'):
                                                                        echo "Rejected By Client";
                                                                    break;
                                                                    default:
                                                                        echo "Under Processing";
                    
                                                                } 
                                                        ?> 
                                                    title="Interest Overdue" readonly>
                                                </div>

                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Payment Status</label>
                                                    <input type="text"  placeholder="Payment Status" name="payment_status" class="form-control" value="<?= $loan_Schedule->paymentStatus ?>" title="Payment Status" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Date Generated</label>
                                                    <input type="text"  placeholder="Date Generated" name="date_generated" class="form-control" value="<?= $loan_Schedule->date_generated ?>" title="Date Generated" readonly>
                                                </div>
                                                
                                            </div>
                                            <table class="table table-striped dt-responsive nowrap">
                                                <thead>
                                                    <tr>
                                                        <th>S/N</th>
                                                        <th>Due Date</th>
                                                        <th>Principal Due</th>
                                                        <th>Interest Due</th>
                                                        <th>Fees Due</th>
                                                        <th>Penalty Due</th>
                                                        
                                                    </tr>
                                                </thead>

                                                <tbody> 
                                                    <?php if(count($repayment_schedule) > 0): ?>    

                                                        <?php $sn = 1; foreach($repayment_schedule as $repayments): ?> 
                                                            <tr>
                                                                <td><?= $sn++; ?></td>
                                                                <td><?= $repayments['dueDate'];?></td>
                                                                <td><?= number_format($repayments['principalDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['interestDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['feesDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['penaltyDue'], 2); ?></td>
                                                            </tr>
                                                        
                                                        <?php endforeach ?> 
                                                    <?php endif ?>
                                                </tbody>
                                            </table>
                                            <div class="form-group">
                                                <div class="col-lg-12 text-center">
                                                    <button type="submit" class="btn btn-success" style="margin:5px;" id="recalcaculate_schedule" title="Recalculate Client Loan Repayment Schedule">Send To Customer Email</button>
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                    
                                    
                                    

                                </form>
                            </div>
                        </div>
                    <!-- END panel-->
                    </div>
 
                </div>
        </section>
	

		
		
		