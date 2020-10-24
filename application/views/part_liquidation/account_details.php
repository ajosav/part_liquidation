<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

        <section class="main-content container">
    
            <!--page header end-->
				<div class="col-lg-12">
                    <!-- START panel-->
                    <div class="panel panel-default">
                        <div class="panel-heading">Loan Details </div>
                            <div class="panel-body">
                                <div class="col-md-12 text-right"><a href="<?= base_url();?>Loan_account/liquidation_history/<?= $id ?>" class="btn btn-success"><i class="fa fa-history"></i> Liquidation History</a></div>
                                <form class="form-horizontal" method="post" id="recalculate" action="<?= base_url() ?>Loan_account/recalculate_schedule" style="padding: 10px 40px;">
                    
                                    <div class="row">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <div class="col-md-4">
                                                    <label for="data_file">Loan ID</label>
                                                    <input type="hidden" name="max_tenor" id="max_tenor" value="<?= count($max_tenor) - 1 ?>">
                                                    <input type="hidden" name="accountHolderKey" id="accountHolderKey" value="<?= $accountHolderKey?>">
                                                    <input type="hidden" name="productTypeKey" id="productTypeKey" value="<?= $productTypeKey?>">
                                                    <input type="hidden" name="interestBalance" id="productTypeKey" value="<?= $interestBalance?>">
                                                    <input type="email"  placeholder="Loan ID" name="loan_id" class="form-control" value="<?= $id ?>" title="Client Loan ID" readonly>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="date_generated">Product Name</label>
                                                    <input type="text"  placeholder="Product Name" name="loanName" class="form-control" value="<?= $loanName ?>" title="Loan Product Name" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Interest Rate</label>
                                                    <input type="text"  placeholder="Interest Rate" name="interest_rate" class="form-control" value="<?= $interestRate ?>" title="Account Interest Rate" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Interest Accrued</label>
                                                    <input type="text"  placeholder="Interest Accrued" name="interest_accrued" class="form-control" value="<?= $accruedInterest ?>" title="Interest Accrued" readonly>
                                                </div>
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Interest Overdue</label>
                                                    <input type="text"  placeholder="Intrest Overdue" name="interest_overdue" class="form-control" value="<?= $interestDue ?>" title="Interest Overdue" readonly>
                                                </div>

                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Principal Balance</label>
                                                    <input type="text"  placeholder="Principal Balance" name="principal_balance" class="form-control" value="<?= $principalBalance ?>" title="Principal Balance" readonly>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Principal Due</label>
                                                    <input type="text"  placeholder="Principal Due" name="principal_due" class="form-control" value="<?= $principalDue ?>" title="Principal Due" readonly>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Penalty Due</label>
                                                    <input type="text"  placeholder="Penalty Due" name="penalty_due" class="form-control" value="<?= $penaltyDue ?>" title="Penalty Due" readonly>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Fees Due</label>
                                                    <input type="text"  placeholder="Fees Due" name="fees_due" class="form-control" value="<?= $feesDue ?>" title="Fees Due" readonly>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-6">
                                                    <label for="date_generated">Outstanding Tenor</label>
                                                    <input type="text"  placeholder="Outstanding Tenor" name="installment" class="form-control" value="<?= $outstanding_repayments ?>" title="Outstanding Tenor" readonly>
                                                </div>
                                                <div class="col-lg-6">
                                                    <label for="date_generated">Next Repayment Due Date</label>
                                                    <input type="text"  placeholder="Next Repayment Due Date" name="n_repayment" class="form-control" value="<?= $next_repayment_due_date ?>" title="Next Repayment Due Date" readonly>
                                                </div>
                                                
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-4">
                                                    <label for="date_generated">Part-Liquidation Amount (&#8358;)</label>
                                                    <input type="text"  placeholder="Part Liquidation Amount" name="part_liq_amount" class="form-control" value="" title="Outstanding Tenor" required>
                                                    <span class="help-block" id="liquidation_amount" style="color: red;"></span>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Repayment Tenor</label>
                                                    <div class="radio">
                                                        <label class="radio-inline">    
                                                            <input type="radio" id="maintain_tenor" name="tenure_type" value="maintain" checked required>Maintain Tenor
                                                        </label>
                                                        <label class="radio-inline">
                                                            <input type="radio" id="new_tenor" name="tenure_type" value="new">New Tenor
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-5">
                                                    <label for="tenor">Repayment Tenor (<?= strtolower($repaymentPeriodUnit) ?>)</label>
                                                    <input type="text"  placeholder="Repayment Tenor" name="repayment_tenor" class="form-control" value="<?= count($max_tenor) - 1 ?>" title="Loan Repayment Tenor" required>
                                                    <span class="help-block" id="payment_tenor" style="color: red;"></span>
                                                </div>
                                                
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-3">
                                                    <label for="date_generated">Transaction Channel</label>
                                                    <select name="transaction_channel" id="channel" class="form-control">
                                                        <?= $transaction_channel ?>
                                                    </select>
                                                </div>
                                                <div class="col-lg-3" id="trans_method_div">
                                                    <label for="trans_method">Transaction Method</label>
                                                    <select name="transaction_method" id="trans_method" class="form-control">
                                                        <?= $transaction_method ?>
                                                    </select>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="tenor">Transaction Date</label>
                                                    <input type="date"  placeholder="Transaction Date" name="transaction_date" class="form-control" title="Transaction Date" required>
                                                    <span class="help-block" id="trans_date" style="color: red;"></span>
                                                </div>
                                                <div class="col-lg-3">
                                                    <label for="tenor">Payment Status</label>
                                                    <div class="radio">
                                                        <label class="radio-inline">    
                                                            <input type="radio" id="Paid" name="payment_status" value="paid" checked required>Paid
                                                        </label>
                                                        <label class="radio-inline">
                                                            <input type="radio" id="unpaid" name="payment_status" value="unpaid">Not Paid
                                                        </label>
                                                    </div>
                                                    
                                                    <span class="help-block" id="trans_date" style="color: red;"></span>
                                                </div>
                                                
                                            </div>
                                            <div class="form-group">
                                                <div class="col-lg-12">
                                                    <label for="comment">comment</label>
                                                    <textarea name="comment" id="comment" cols="30" rows="10" class="form-control"></textarea>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <div class="col-lg-12 text-center">
                                                    <button type="submit" class="btn btn-primary" style="margin:5px;" id="recalcaculate_schedule" title="Recalculate Client Loan Repayment Schedule">Re-calculate Schedule</button>
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
	

		
		
		