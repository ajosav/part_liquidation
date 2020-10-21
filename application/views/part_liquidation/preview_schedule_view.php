<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

        <section class="main-content container">
    
            <!--page header end-->
				<div class="col-lg-12">
                    <!-- START panel-->
                    <div class="panel panel-default">
                        <div class="panel-heading">Repayment Schedule</div>
                            <div class="panel-body">
                                
                                <?php if($new_repayment_due > $mambu_repayment_due):?>
                                    <div class="col-md-12">
                                        <p class="text-danger" style="padding: 5px;"><b>Note: </b>Total due amount per installment is greater. if this is a Remita Client Please take note</p>
                                    </div>

                                <?php endif ?>
                                <form class="form-horizontal" method="post" action="<?= base_url() ?>Loan_account/send_schedule_to_customer" style="padding: 10px 40px;">
                    
                                    <div class="row">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <div class="col-md-4">
                                                    <label for="data_file">Loan ID</label>
                                                    <input type="hidden" name="accountHolderKey" id="accountHolderKey" value="<?= $loan_schedule->accountHolderKey?>">
                                                    <input type="hidden" name="schedule_id" id="schedule_id" value="<?= $loan_schedule->schedule_id?>">
                                                    <input type="email"  placeholder="Loan ID" name="loan_id" class="form-control" value="<?= $loan_schedule->loan_id ?>" title="Client Loan ID" readonly>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="date_generated">Liquidation Amount (&#8358;)</label>
                                                    <input type="text"  placeholder="Liquidation Amount" name="liquidationAmount" class="form-control" value="<?= $loan_schedule->liquidationAmount ?>" title="Liquidation Amount" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Principal Balance (After Liquidation)</label>
                                                    <input type="text"  placeholder="Interest Rate" name="principal_balance" class="form-control" value="<?= $loan_schedule->principalBalance ?>" title="Principal Balance" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Loan Tenure</label>
                                                    <input type="text"  placeholder="Interest Accrued" name="interest_accrued" class="form-control" value="<?= $loan_schedule->tenure ?>" title="Interest Accrued" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Status</label>
                                                    <input type="text"  placeholder="Intrest Overdue" name="interest_overdue" class="form-control" value="
                                                        <?php
                                                            switch($loan_schedule->status) {
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
                                                                        echo "Rescheduled";
                    
                                                                } 
                                                        ?> "
                                                    title="Interest Overdue" readonly>
                                                </div>

                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Payment Status</label>
                                                    <input type="text"  placeholder="Payment Status" name="payment_status" class="form-control" value="<?= $loan_schedule->paymentStatus ?>" title="Payment Status" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Date Generated</label>
                                                    <input type="text"  placeholder="Date Generated" name="date_generated" class="form-control" value="<?= $loan_schedule->date_generated ?>" title="Date Generated" readonly>
                                                </div>
                                                
                                            </div>
                                            <?php if(count($repayment_schedule) > 0): ?>    
                                            <table class="table table-striped dt-responsive nowrap">
                                                <thead>
                                                    <tr>
                                                        <th>S/N</th>
                                                        <th>Due Date</th>
                                                        <th>Principal Due</th>
                                                        <th>Interest Due</th>
                                                        <th>Fees Due</th>
                                                        <th>Penalty Due</th>
                                                        <th>Total Due</th>
                                                        
                                                    </tr>
                                                </thead>

                                                <tbody> 
                                                    

                                                        <?php 
                                                            $sn = 1; 
                                                            $t_principal = 0;
                                                            $t_interest = 0;
                                                            $t_fees = 0;
                                                            $t_penalty = 0;

                                                            foreach($repayment_schedule as $repayments): ?> 
                                                            <tr>
                                                                <td><?= $sn++; ?></td>
                                                                <td><?= $repayments['dueDate'];?></td>
                                                                <td><?= number_format($repayments['principalDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['interestDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['feesDue'], 2); ?></td>
                                                                <td><?= number_format($repayments['penaltyDue'], 2); ?></td>
                                                                <td><?= number_format(($repayments['penaltyDue'] + $repayments['principalDue'] + $repayments['interestDue'] + $repayments['feesDue']), 2); ?></td>
                                                            
                                                                <?php 
                                                                    $t_principal += $repayments['principalDue'];
                                                                    $t_interest += $repayments['interestDue'];
                                                                    $t_fees += $repayments['feesDue'];
                                                                    $t_penalty += $repayments['penaltyDue'];
                                                                
                                                                ?>
                                                            </tr>

                                                        
                                                        <?php endforeach ?> 
                                                   
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="2">Totals</td>
                                                        <td><?=number_format($t_principal, 2); ?></td>
                                                        <td><?=number_format($t_interest, 2); ?></td>
                                                        <td><?=number_format($t_fees, 2); ?></td>
                                                        <td><?=number_format($t_penalty, 2); ?></td>
                                                        <td><?=number_format(($t_principal + $t_interest + $t_fees + $t_penalty), 2); ?></td>
                                                    </tr>
                                                </tfoot>
                                            </table> 
                                            <?php endif ?>
                                            <div class="form-group">
                                                <?php
                                                    $today = date('Y-m-d H:i:s');
                                                    $back_date = date('Y-m-d H:i:s', strtotime("-24 hours", strtotime($today)));
                                                    $db_forwardDate = date('Y-m-d H:i:s', strtotime("+24 hours", strtotime($loan_schedule->date_generated)));
                                                    if(($loan_schedule->status == 2) || ($loan_schedule->status == 3) || $back_date > $db_forwardDate ) :?>
                                                    <div class="col-lg-12 text-center">
                                                        <a  href="#" disabled class="btn btn-disable" style="margin:5px;" title="Cannot Send Link">No action required</a>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-lg-12 text-center">
                                                        <button type="submit" class="btn btn-success" style="margin:5px;" id="recalcaculate_schedule" title="Recalculate Client Loan Repayment Schedule">Send To Customer Email</button>
                                                    </div>
                                                <?php endif ?>
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
	

		
		
		