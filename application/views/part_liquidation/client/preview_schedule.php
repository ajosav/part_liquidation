<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

        <section class="main-content container">

        <!--page header start-->
    <div class="page-header">
        <div class="row">
            <div class="col-sm-6">
                
                <h3>Hello <?= $client_details['client']['firstName'] ?></h3>
                <p>Please see below the new repayment schedule based on your Part Liquidation Request</p>
            </div>
            <div class="col-sm-6 text-right">
                
            </div>
        </div>
    </div>
    <!--page header end-->
    
            <!--page header end-->
				<div class="col-lg-12">
                    <!-- START panel-->
                    <div class="panel panel-default">
                        <div class="panel-heading">Repayment Schedule</div>
                            <div class="panel-body">
                                <form class="form-horizontal" method="post" action="<?= base_url() ?>Client/accept_schedule" style="padding: 10px 40px;">
                    
                                    <div class="row">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <div class="col-md-4">
                                                    <label for="data_file">Loan ID</label>
                                                    
                                                    <input type="hidden" name="client_fname" value="<?= $client_details['client']['firstName'] ?>">
                                                    <input type="hidden" name="client_lname" value="<?= $client_details['client']['lastName'] ?>">
                                                    <input type="hidden" name="client_email" value="<?= $client_details['client']['emailAddress'] ?>">
                                                    <input type="hidden" name="client_phone" value="<?= $client_details['client']['mobilePhone1'] ?>">
                                                    <input type="hidden" name="accountHolderKey" id="accountHolderKey" value="<?= $loan_schedule->accountHolderKey?>">
                                                    <input type="hidden" name="schedule_id" id="schedule_id" value="<?= $loan_schedule->schedule_id?>">
                                                    <input type="text"  placeholder="Loan ID" name="loan_id" class="form-control" value="<?= $loan_schedule->loan_id ?>" title="Client Loan ID" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="date_generated">Liquidation Amount</label>
                                                    <input type="text"  placeholder="Liquidation Amount" name="liquidationAmount" class="form-control" value="<?= number_format($loan_schedule->liquidationAmount, 2) ?>" title="Liquidation Amount" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Date Generated</label>
                                                    <input type="text"  placeholder="Date Generated" name="date_generated" class="form-control" value="<?= $loan_schedule->date_generated ?>" title="Date Generated" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Principal Balance (After Liquidation)</label>
                                                    <input type="text"  placeholder="Interest Rate" name="principal_balance" class="form-control" value="<?= number_format($loan_schedule->principalBalance, 2) ?>" title="Principal Balance" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Loan Tenure</label>
                                                    <input type="text"  placeholder="Interest Accrued" name="interest_accrued" class="form-control" value="<?= $loan_schedule->tenure ?>" title="Interest Accrued" readonly>
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
                                                                        <td><?= number_format(($repayments['principalDue'] + $repayments['feesDue'] + $repayments['penaltyDue'] + $repayments['interestDue']), 2); ?></td>
                                                                    </tr>
                                                                    <?php 
                                                                        $t_principal += $repayments['principalDue'];
                                                                        $t_interest += $repayments['interestDue'];
                                                                        $t_fees += $repayments['feesDue'];
                                                                        $t_penalty += $repayments['penaltyDue'];
                                                                    
                                                                    ?>
                                                            
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
                                            <?php if(($loan_schedule->status != 3) &&  ($loan_schedule->status != 2)): ?>
                                                <div class="form-group">
                                                    <div class="col-lg-12 text-center">
                                                        <button type="submit" id="reject_liquidation" class="btn btn-danger" style="margin:5px;" id="reject_liquidation" title="Reject New Loan Schedule">Reject</button>
                                                        <button type="submit" class="btn btn-success" style="margin:5px;" id="accept_liquidation" title="Accept new loan schedule">Accept</button>
                                                    </div>
                                                </div>
                                            <?php endif ?>

                                        </div>

                                    </div>
                                    
                                    
                                    

                                </form>
                            </div>
                        </div>
                    <!-- END panel-->
                    </div>


                    

                    <!-- Modal -->
                    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLongTitle">Reject Liquidation scheduled on loan <code> <?= $loan_schedule->loan_id?> </code></h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="rejection_form">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <input type="hidden" name="loan_id" value="<?= $loan_schedule->loan_id ?>">
                                                <input type="hidden" name="client_fname" value="<?= $client_details['client']['firstName'] ?>">
                                                <input type="hidden" name="client_lname" value="<?= $client_details['client']['lastName'] ?>">
                                                <input type="hidden" name="client_email" value="<?= $client_details['client']['emailAddress'] ?>">
                                                <input type="hidden" name="client_phone" value="<?= $client_details['client']['mobilePhone1'] ?>">
                                                <div class="col-lg-12">
                                                    <label for="tenor">Rejection Reason</label>
                                                    <div class="radio">
                                                        <label class="radio-inline">    
                                                            <input type="radio" id="repayment" name="rejection_state" value="refund" checked required>Refund
                                                        </label>
                                                        <label class="radio-inline">
                                                            <input type="radio" id="refund" name="rejection_state" value="repayment">Apply as Repayment
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="form-group col-12">
                                                    <label for="rejection_reason" class="panel-heading">Rejection Note</label>
                                                    <textarea class="form-control" name="rejection_reason" id="rejection_reason"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="submit_rejection" data-action="reject" data-id="<?= $loan_schedule->schedule_id?>">Submit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                
                </div>
        </section>
	

		
		
		