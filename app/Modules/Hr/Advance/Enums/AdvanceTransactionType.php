<?php
namespace App\Modules\Hr\Advance\Enums;
enum AdvanceTransactionType:string { case Disbursement='disbursement'; case Repayment='repayment'; case PayrollDeduction='payroll_deduction'; case Adjustment='adjustment'; public function label():string{return match($this){self::Disbursement=>'Ödeme',self::Repayment=>'Geri ödeme',self::PayrollDeduction=>'Bordro mahsubu',self::Adjustment=>'Düzeltme'};} }
