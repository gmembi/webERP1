<?php
/* GoodsReceived.php */
/* Entry of items received against purchase orders */

/* Session started in header.php for password checking and authorisation level check */
include('includes/DefinePRClass.php');
//include('includes/DefineSerialItems.php');
include('includes/session.php');
include('includes/SQL_CommonFunctions.inc');

/*The identifier makes this goods received session unique so cannot get confused
 * with other sessions of goods received on the same machine/browser
 * The identifier only needs to be unique for this php session, so a
 * unix timestamp will be sufficient.
 */

if (empty($_GET['identifier'])) {
	$identifier=date('U');
} else {
	$identifier=$_GET['identifier'];
}
$Title = _('Post Payment Request');
$ViewTopic = 'General Ledger';
$BookMark = 'Postpayment';
include('includes/header.php');

echo '<a href="'. $RootPath . '/PR_SelectOSPayRequest.php">' . _('Back to Payment Request'). '</a>
	<br />';

if (isset($_GET['PRNumber']) AND $_GET['PRNumber']<=0 AND !isset($_SESSION['PR'.$identifier])) {
	/* This page can only be called with a purchase order number for invoicing*/
	echo '<div class="centre">
			<a href= "' . $RootPath . '//PR_SelectOSPayRequest.php">'. _('Select a payment request to receive').'</a>
		</div>
		<br />' .  _('This page can only be opened if a payment request has been selected. Please select a purchase order first');

	include ('includes/footer.php');
	exit;
} elseif (isset($_GET['PRNumber'])
			AND !isset($_POST['Update'])) {
/*Update only occurs if the user hits the button to refresh the data and recalc the value of goods recd*/

	$_GET['ModifyOrderNumber'] = intval($_GET['PRNumber']);
	include('includes/PR_ReadInPayment.inc');
} elseif (isset($_POST['Update'])
			OR isset($_POST['ProcessGoodsReceived'])) {

/* if update quantities button is hit page has been called and ${$Line->LineNo} would have be
 set from the post to the quantity to be received */

	// foreach ($_SESSION['PR'.$identifier]->LineItems as $Line) {
	// 	$RecvQty = round(filter_number_format($_POST['RecvQty_' . $Line->LineNo]),$Line->DecimalPlaces);
	// 	if (!is_numeric($RecvQty)) {
	// 		$RecvQty = 0;
	// 	}
	// 	$_SESSION['PR'.$identifier]->LineItems[$Line->LineNo]->ReceiveQty = $RecvQty;
	// 	if (isset($_POST['Complete_' . $Line->LineNo])) {
	// 		$_SESSION['PR'.$identifier]->LineItems[$Line->LineNo]->Completed = 1;
	// 	} else {
	// 		$_SESSION['PR'.$identifier]->LineItems[$Line->LineNo]->Completed = 0;
	// 	}
	// }
}

if ($_SESSION['PR'.$identifier]->Status != 'Printed') {
	prnMsg( _('Payment Request  must have a status of Printed before they can be received').'.<br />' .
		_('Payment Request number') . ' ' . $_GET['PRNumber'] . ' ' . _('has a status of') . ' ' . _($_SESSION['PR'.$identifier]->Status), 'warn');
	include('includes/footer.php');
	exit;
}

// Always display quantities received and recalc balance for all items on the order
echo '<p class="page_title_text"><img alt="" src="', $RootPath, '/css/', $Theme,
	'/images/supplier.png" title="', // Icon image.
	_('Post '), '" /> ', // Icon title.
	_('Post Payment Request'), ' : ', $_SESSION['PR'.$identifier]->PaymentNo, ' </p>';// Page title.

echo '<form action="', htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8'), '?identifier=', $identifier, '" id="form1" method="post">',
	'<div>',
	'<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

if (!isset($_POST['ProcessGoodsReceived'])) {
	if (!isset($_POST['DefaultReceivedDate']) AND !isset($_SESSION['PO' . $identifier]->DefaultReceivedDate)) {
		/* This is meant to be the date the goods are received - it does not make sense to set this to the date that we requested delivery in the purchase order - I have not applied your change here Tim for this reason - let me know if I have it wrong - Phil */
		$_POST['DefaultReceivedDate'] = Date($_SESSION['DefaultDateFormat']);
		$_SESSION['PO' . $identifier]->DefaultReceivedDate = $_POST['DefaultReceivedDate'];
	} else {
		if (isset($_POST['DefaultReceivedDate']) AND is_date($_POST['DefaultReceivedDate'])) {
			$_SESSION['PO' . $identifier]->DefaultReceivedDate = $_POST['DefaultReceivedDate'];
		} elseif(isset($_POST['DefaultReceivedDate']) AND !is_date($_POST['DefaultReceivedDate'])) {
			prnMsg(_('The default received date is not a date format'),'error');
			$_POST['DefaultReceivedDate'] = Date($_SESSION['DefaultDateFormat']);
		}
	}

	echo '<table class="selection">
			<tr>
				<td>' .  _('Date Payment Posting'). ':</td>
				<td><input type="text" class="date" maxlength="10" size="11" onchange="return isDate(this, this.value, '."'".
			$_SESSION['DefaultDateFormat']."'".')" name="DefaultReceivedDate" value="' . $_SESSION['PO' . $identifier]->DefaultReceivedDate . '" /></td>
			</tr>
		</table>
		<br />',
		'<table cellpadding="2" class="selection">
			<tr><th colspan="2">&nbsp;</th>
				<th class="centre" colspan="12"><b>', _('Payment Details'), '</b></th>';
	
	echo	'</tr>
			<tr>
            <th>' . _('Source') . '</th>
            <th>' . _('Group') . '</th>
            <th>' . _('Section')  . '</th>
            <th>' . _('Cost') . '</th>
            <th>' . _('GL Account') . '</th>
            <th>' . _('Narrative') . '</th>    
            <th>' . _('Amount')  . '</th>';

	echo '</tr>';
	/*show the line items on the order with the quantity being received for modification */

	$_SESSION['PR'.$identifier]->Total = 0;
}

if (count($_SESSION['PR'.$identifier]->LineItems)>0 and !isset($_POST['ProcessGoodsReceived'])) {

	foreach ($_SESSION['PR'.$identifier]->LineItems as $LnItm) {

	/*  if ($LnItm->ReceiveQty==0) {   /*If no quantities yet input default the balance to be received
			$LnItm->ReceiveQty = $LnItm->QuantityOrd - $LnItm->QtyReceived;
		}
	*/

	/*Perhaps better to default quantities to 0 BUT.....if you wish to have the receive quantities
	default to the balance on order then just remove the comments around the 3 lines above */

	//Setup & Format values for LineItem display

		$LineTotal = $LnItm->Amount ;
		$_SESSION['PR'.$identifier]->Total = $_SESSION['PR'.$identifier]->Total + $LineTotal;
		// $DisplaySupplierQtyOrd = locale_number_format($LnItm->Quantity/$LnItm->ConversionFactor,$LnItm->DecimalPlaces);
		// $DisplaySupplierQtyRec = locale_number_format($LnItm->QtyReceived/$LnItm->ConversionFactor,$LnItm->DecimalPlaces);
		// $DisplayQtyOrd = locale_number_format($LnItm->Quantity,$LnItm->DecimalPlaces);
		// $DisplayQtyRec = locale_number_format($LnItm->QtyReceived,$LnItm->DecimalPlaces);
		$DisplayLineTotal = locale_number_format($LineTotal,$_SESSION['PR'.$identifier]->CurrDecimalPlaces);
		//  if ($LnItm->Price > 1) {
		// 	$DisplayPrice = locale_number_format($LnItm->Price,$_SESSION['PR'.$identifier]->CurrDecimalPlaces);
		// } else {
		// 	$DisplayPrice = locale_number_format($LnItm->Price,4);
		// }


		//Now Display LineItem
		// $SupportedImgExt = array('png','jpg','jpeg');
		// $imagefile = reset((glob($_SESSION['part_pics_dir'] . '/' . $LnItm->StockID . '.{' . implode(",", $SupportedImgExt) . '}', GLOB_BRACE)));
		// if ($imagefile) {
		// 	$ImageLink = '<a href="' . $imagefile . '" target="_blank">' .  $LnItm->StockID . '</a>';
		// } else {
		// 	$ImageLink = $LnItm->StockID;
		// }

		echo '<tr class="striped_row">
        <td>'.  stripslashes($LnItm->sourcedescription). '</td>
        <td>'. $LnItm->groupname . '</td>
        <td>'. $LnItm->sectionname . '</td>
        <td>'. $LnItm->costname . '</td>
        <td>'. $LnItm->GLCode . '</td>
        <td>'. $LnItm->Narrative . '</td>
        <td>'. $LnItm->Amount . '</td>


		</tr>';
	}//foreach(LineItem)
	$DisplayTotal = locale_number_format($_SESSION['PR'.$identifier]->Total,$_SESSION['PR'.$identifier]->CurrDecimalPlaces);
	if ($_SESSION['ShowValueOnGRN'] == 1) {
		echo '<tr>
				<td class="number" colspan="14"><b>', _('Total Payment'), '</b></td>
				<td class="number"><b>',  $DisplayTotal, '</b></td>
			</tr>';
	}
	echo '</table>';

}//If count(LineItems) > 0




if (isset($_POST['ProcessGoodsReceived']) AND $InputError == false) {
    $PeriodNo = GetPeriod($_POST['DefaultReceivedDate']);
/* SQL to process the postings for goods received... */
/* Company record set at login for information on GL Links and debtors GL account*/


	if ($_SESSION['CompanyRecord']==0) {
		/*The company data and preferences could not be retrieved for some reason */
        echo '</div>';
        echo '</form>';
		prnMsg(_('The company information and preferences could not be retrieved') . ' - ' . _('see your system administrator') , 'error');
		include('includes/footer.php');
		exit;
    }
    


/* *********************** BEGIN SQL TRANSACTIONS *********************** */

	$Result = DB_Txn_Begin();

if ($_SESSION['PR'.$identifier]->SupplierID=='') {
    
    $TransNo = GetNextTransNo(1);
    $Transtype = 1;
	$PeriodNo = GetPeriod($_POST['DefaultReceivedDate']);
	$_POST['DefaultReceivedDate'] = FormatDateForSQL($_POST['DefaultReceivedDate']);

	
    if ($_SESSION['CompanyRecord']['gllink_creditors']==1){ /* then enter GLTrans */
        $TotalAmount=0;
        foreach ($_SESSION['PR'.$identifier]->LineItems as $PaymentItem) {

             /*The functional currency amount will be the
             payment currenct amount  / the bank account currency exchange rate  - to get to the bank account currency
             then / the functional currency exchange rate to get to the functional currency */
            if ($PaymentItem->chequeno=='') $PaymentItem->chequeno=0;
            $SQL = "INSERT INTO gltrans (type,
                                        typeno,
                                        trandate,
                                        periodno,
                                        account,
                                        narrative,
                                        amount,
                                        chequeno,
                                        locgroup,
                                        locsection,
                                        loccost,
                                        source) ";
             $SQL= $SQL . "VALUES (1,
                '" . $TransNo . "',
                '" . $_POST['DefaultReceivedDate'] . "',
                '" . $PeriodNo . "',
                '" . $PaymentItem->GLCode . "',
                '" . ('PV Number ') . $PaymentItem->PaymentNo . ' - ' . $PaymentItem->Narrative . "',
                " . $PaymentItem->Amount/$PaymentItem->ExRate/$PaymentItem->FunctionalExrate . ",
                '" . $PaymentItem->chequeno ."',
                '" . $PaymentItem->LGroup ."',
                '" . $PaymentItem->LSection. "',
                '" . $PaymentItem->LCost . "',
                '" . $PaymentItem->source . "'
                )";
            $ErrMsg = _('Cannot insert a GL entry for the payment using the SQL');
            $result = DB_query($SQL,$ErrMsg,_('The SQL that failed was'),true);

            $TotalAmount += $PaymentItem->Amount;
        }

        $_SESSION['PR'.$identifier]->Amount = $TotalAmount;
        $_SESSION['PR'.$identifier]->Discount=0;
    }

    //Run through the GL postings to check to see if there is a posting to another bank account (or the same one) if there is then a receipt needs to be created for this account too

    foreach ($_SESSION['PR'.$identifier]->LineItems as $PaymentItem) {

        if (in_array($PaymentItem->GLCode, $BankAccounts)) {

            /*Need to deal with the case where the payment from one bank account could be to a bank account in another currency */

            /*Get the currency and rate of the bank account transferring to*/
            $SQL = "SELECT currcode, rate
                    FROM bankaccounts INNER JOIN currencies
                    ON bankaccounts.currcode = currencies.currabrev
                    WHERE accountcode='" . $PaymentItem->GLCode . "'";
            $TrfToAccountResult = DB_query($SQL,$db);
            $TrfToBankRow = DB_fetch_array($TrfToAccountResult) ;
            $TrfToBankCurrCode = $TrfToBankRow['currcode'];
            $TrfToBankExRate = $TrfToBankRow['rate'];

            if ($_SESSION['PR'.$identifier]->CurrCode == $TrfToBankCurrCode){
            /*Make sure to use the same rate if the transfer is between two bank accounts in the same currency */
                $TrfToBankExRate = $PaymentItem->FunctionalExRate;
            }
//
//					/*Consider an example
//					 functional currency NZD
//					 bank account in AUD - 1 NZD = 0.90 AUD (FunctionalExRate)
//					 paying USD - 1 AUD = 0.85 USD  (ExRate)
//					 to a bank account in EUR - 1 NZD = 0.52 EUR
//
//					 oh yeah - now we are getting tricky!
//					 Lets say we pay USD 100 from the AUD bank account to the EUR bank account
//
//					 To get the ExRate for the bank account we are transferring money to
//					 we need to use the cross rate between the NZD-AUD/NZD-EUR
//					 and apply this to the
//
//					 the payment record will read
//					 exrate = 0.85 (1 AUD = USD 0.85)
//					 amount = 100 (USD)
//					 functionalexrate = 0.90 (1 NZD = AUD 0.90)
//
//					 the receipt record will read
//
//					 amount 100 (USD)
//					 exrate    (1 EUR =  (0.85 x 0.90)/0.52 USD)
//					 					(ExRate x FunctionalExRate) / USD Functional ExRate
//					 functionalexrate =     (1NZD = EUR 0.52)
//
//				*/

            $ReceiptTransNo = GetNextTransNo( 2, $db);
            $SQL= "INSERT INTO banktrans (transno,
                                            type,
                                            bankact,
                                            ref,
                                            chequeno,
                                            exrate,
                                            functionalexrate,
                                            transdate,
                                            banktranstype,
                                            amount,
                                            currcode)
                VALUES ('" . $ReceiptTransNo . "',
                    2,
                    '" . $PaymentItem->Bankact . "',
                    '" . substr(_('Act Transfer From ') . $PaymentItem->Amount . ' - ' . $PaymentItem->Narrative,0,50) . "',
                    '" . $PaymentItem->chequeno . "',
                    " . ((locale_number_format($PaymentItem->ExRate))). ",
                    '" . $TrfToBankExRate . "',
                    '" . $_POST['DefaultReceivedDate'] . "',
                    '" . $PaymentItem->PaymentTypes . "',
                    '" . $PaymentItem->Amount . "',
                    '" . $_SESSION['PR'.$identifier]->CurrCode . "'
                )";
            $ErrMsg = _('Cannot insert a bank transaction because');
            $DbgMsg =  _('Cannot insert a bank transaction with the SQL');
            $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);

        }
    }
} 

else {
    /*Its a supplier payment type 22 */
     //$CreditorTotal = ((locale_number_format($_SESSION['PR'.$identifier]->Amount))/locale_number_format($_SESSION['PR'.$identifier]->ExRate))/locale_number_format($_SESSION['PR'.$identifier]->FunctionalExrate);

          $TransNo = GetNextTransNo(22, $db);
          $Transtype = 22;
          $PeriodNo = GetPeriod($_POST['DefaultReceivedDate']);
          $_POST['DefaultReceivedDate'] = FormatDateForSQL($_POST['DefaultReceivedDate']);
    foreach ($_SESSION['PR'.$identifier]->LineItems as $PaymentItem){
        $CreditorTotal = locale_number_format($PaymentItem->Amount)/locale_number_format($_SESSION['PR'.$identifier]->ExRate)/locale_number_format($_SESSION['PR'.$identifier]->FunctionalExrate);
          /* Create a SuppTrans entry for the supplier payment */
          $SQL = "INSERT INTO supptrans (transno,
                                          type,
                                          supplierno,
                                          trandate,
                                          inputdate,
                                          suppreference,
                                          rate,
                                          ovamount,
                                          transtext) ";
          $SQL = $SQL . "VALUES ('" . $TransNo . "',
                  22,
                  '" . $_SESSION['PR'.$identifier]->SupplierID . "',
                  '" . $_POST['DefaultReceivedDate'] . "',
                  '" . date('Y-m-d H-i-s') . "',
                  '" . $PaymentItem->PaymentTypes . "',
                   " . (locale_number_format($PaymentItem->FunctionalExrate)."/".locale_number_format($PaymentItem->ExRate)) . ",
                  -" . $PaymentItem->Amount. ",
                  '" . ('PV Number ') . $_SESSION['PR'.$identifier]->PaymentNo . ' - ' . $PaymentItem->Narrative . "'
              )";

          $ErrMsg =  _('Cannot insert a payment transaction against the supplier because');
          $DbgMsg = _('Cannot insert a payment transaction against the supplier using the SQL');
          $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);

          /*Update the supplier master with the date and amount of the last payment made */
          $SQL = "UPDATE suppliers
                  SET	lastpaiddate = '" . $_POST['DefaultReceivedDate'] . "',
              lastpaid='" . locale_number_format($_SESSION['PR'.$identifier]->Amount) ."'
              WHERE suppliers.supplierid='" . $_SESSION['PR'.$identifier]->SupplierID . "'";



          $ErrMsg = _('Cannot update the supplier record for the date of the last payment made because');
          $DbgMsg = _('Cannot update the supplier record for the date of the last payment made using the SQL');
          $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);

          $_SESSION['PR'.$identifier]->Narrative = $_SESSION['PR'.$identifier]->SupplierID . '-' . $PaymentItem->Narrative;

          if ($_SESSION['CompanyRecord']['gllink_creditors']==1){ /* then do the supplier control GLTrans */
          /* Now debit creditors account with payment + discount */

              $SQL="INSERT INTO gltrans ( type,
                          typeno,
                          trandate,
                          periodno,
                          account,
                          narrative,
                          amount)
                      VALUES (22,
                          '" . $TransNo . "',
                          '" . $_POST['DefaultReceivedDate'] . "',
                          '" . $PeriodNo . "',
                          '" . $_SESSION['CompanyRecord']['creditorsact'] . "',
                          '" . ('PV Number ') . $_SESSION['PR'.$identifier]->PaymentNo . ' - ' . $PaymentItem->Narrative . "',
                          '" . $PaymentItem->Amount/$PaymentItem->ExRate/$PaymentItem->FunctionalExrate . "')";
              $ErrMsg = _('Cannot insert a GL transaction for the creditors account debit because');
              $DbgMsg = _('Cannot insert a GL transaction for the creditors account debit using the SQL');
              $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);

              if ($_SESSION['PR'.$identifier]->Discount !=0){
                  /* Now credit Discount received account with discounts */
                  $SQL="INSERT INTO gltrans ( type,
                              typeno,
                              trandate,
                              periodno,
                              account,
                              narrative,
                              amount) ";
                  $SQL=$SQL . "VALUES (22,
                      '" . $TransNo . "',
                      '" . $_POST['DefaultReceivedDate'] . "',
                      '" . $PeriodNo . "',
                      '" . $_SESSION['CompanyRecord']['pytdiscountact'] . "',
                      '" . ('PV Number ') . $PaymentItem->PaymentNo . ' - ' . $_SESSION['PR'.$identifier]->Narrative . "',
                      -" . locale_number_format($_SESSION['PR'.$identifier]->Discount)/locale_number_format($_SESSION['PR'.$identifier]->ExRate)/locale_number_format($_SESSION['PR'.$identifier]->FunctionalExrate) . "
                    )";
                  $ErrMsg = _('Cannot insert a GL transaction for the payment discount credit because');
                  $DbgMsg = _('Cannot insert a GL transaction for the payment discount credit using the SQL');
                  $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);
              } // end if discount
          } // end if gl creditors
                      $_SESSION['PR'.$identifier]->Amount = $CreditorTotal;
      } // end if supplier


    }

    if ($_SESSION['CompanyRecord']['gllink_creditors']==1){ /* then do the common GLTrans */
        foreach ($_SESSION['PR'.$identifier]->LineItems as $PaymentItem) {
        if ($PaymentItem->Amount !=0){
            /* Bank account entry first */
            $SQL = "INSERT INTO gltrans ( type,
                        typeno,
                        trandate,
                        periodno,
                        account,
                        narrative,
                        amount) ";
            $SQL = $SQL . "VALUES ('" . $Transtype . "',
                    '" . $TransNo . "',
                    '" . $_POST['DefaultReceivedDate'] . "',
                    '" . $PeriodNo . "',
                    '" . $PaymentItem->Bankact . "',
                    '" . ('PV Number ') . $_SESSION['PR'.$identifier]->PaymentNo . ' - ' . $PaymentItem->Narrative . "',
                    -" . $PaymentItem->Amount/$PaymentItem->ExRate/$PaymentItem->FunctionalExrate . ")";   
            
            $ErrMsg =  _('Cannot insert a GL transaction for the bank account credit because');
            $DbgMsg =  _('Cannot insert a GL transaction for the bank account credit using the SQL');
            $result = DB_query($SQL,$ErrMsg,$DbgMsg,true);
        }
        }
    }
    foreach ($_SESSION['PR'.$identifier]->LineItems as $PaymentItem) {
    /*now enter the BankTrans entry */
		if ($Transtype==22) {
			$SQL="INSERT INTO banktrans (transno,
					type,
					bankact,
					ref,
					chequeno,
					exrate,
					functionalexrate,
					transdate,
					banktranstype,
					amount,
					currcode) ";
			$SQL= $SQL . "VALUES ('" . $TransNo . "',
				'" . $Transtype . "',
				'" . $PaymentItem->Bankact . "',
				'" . ('PV Number ') . $_SESSION['PR'.$identifier]->PaymentNo . ' - ' . $_SESSION['PR'.$identifier]->Narrative . "',
				'" . $_SESSION['PR'.$identifier]->chequeno . "',
				'" . locale_number_format($PaymentItem->ExRate) . "',
				'" . locale_number_format($PaymentItem->FunctionalExrate) . "',
				'" . $_POST['DefaultReceivedDate'] . "',
				'" . $PaymentItem->PaymentTypes . "',
				-" . $PaymentItem->Amount . ",
				'" . $_SESSION['PR'.$identifier]->Currency . "'
			)";

			$ErrMsg = _('Cannot insert a bank transaction because');
			$DbgMsg = _('Cannot insert a bank transaction using the SQL');
			$result = DB_query($SQL,$ErrMsg,$DbgMsg,true);
		} else {
				$SQL="INSERT INTO banktrans (transno,
					type,
					bankact,
					ref,
					chequeno,
					exrate,
					functionalexrate,
					transdate,
					banktranstype,
					amount,
					currcode) ";
				$SQL= $SQL . "VALUES ('" . $TransNo . "',
					'" . $Transtype . "',
					'" . $PaymentItem->Bankact . "',
					'" . ('PV Number ') . $_SESSION['PR'.$identifier]->PaymentNo . ' - ' . $PaymentItem->Narrative . "',
					'" . $PaymentItem->chequeno . "',
					'" . locale_number_format($PaymentItem->ExRate) . "',
					'" . locale_number_format($PaymentItem->FunctionalExrate) . "',
					'" . $_POST['DefaultReceivedDate'] . "',
					'" . $PaymentItem->PaymentTypes . "',
					-" . $PaymentItem->Amount . ",
					'" . $_SESSION['PR'.$identifier]->Currency . "'
				)";

				$ErrMsg = _('Cannot insert a bank transaction because');
				$DbgMsg = _('Cannot insert a bank transaction using the SQL');
				$result = DB_query($SQL,$ErrMsg,$DbgMsg,true);
			}
		}
    $sql="UPDATE payrequest
        SET status='Completed'
            WHERE PaymentNo='" .  $_SESSION['PR'.$identifier]->PaymentNo  . "'";
      $result=DB_query($sql);

	$Result = DB_Txn_Commit();
	$PONo = $_SESSION['PR'.$identifier]->PaymentNo;
	unset($_SESSION['PR'.$identifier]->LineItems);
	unset($_SESSION['PR'.$identifier]);
	unset($_POST['ProcessGoodsReceived']);

	echo '<br />
		<div class="centre">
			'. prnMsg(_('Payment Request '). ' '. $PONo .' '. _('has been processed'),'success') . '
            <br />
            
			<a href="' . $RootPath . '/PR_SelectOSPayRequest.php">' . _('Select a different payment request for posting'). '</a>
		</div>';
/*end of process goods received entry */
    echo '</div>';
    echo '</form>';
	include('includes/footer.php');
	exit;

} else { /*Process Goods received not set so show a link to allow mod of line items on order and allow input of date goods received*/

	echo '<br />
		
		<br />
		<div class="centre">
			
			<br />
			<input type="submit" name="ProcessGoodsReceived" value="' . _('Process Payment Post') . '" />
		</div>';
}
echo '</div>';
echo '</form>';
include('includes/footer.php');
?>
