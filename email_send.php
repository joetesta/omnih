<?php

if(isset($_SERVER['REMOTE_ADDR'])){
  die("This program should only be run from cmd line! Exiting...");
}

# include our security and db connection:
require_once('src/MarketplaceWebServiceOrders/Samples/orders.db.inc');

$sent_today = 0;
$last_id = 0;
$recipients = array();

include 'Mail.php';
include 'Mail/mime.php' ;

while( $sent_today < 500 ){
  # Look for highest priority email scheduled that are sending or pending
  $query = "SELECT s.id, c.subject, c.body_text, c.body_html
   FROM email_schedule s
   JOIN email_content c
    ON s.content_id = c.id
   WHERE s.start_time < NOW()
    AND s.status IN ('pending','sending')
   ORDER BY s.priority LIMIT 1";

  if ($result = $mysqli->query($query)) {
    if ($row = $result->fetch_row()) {
      print "DEBUG made it here 1\n";
      $id = $row[0];
      $subject = $row[1];
      $body_text = $row[2];
      $body_html = $row[3];
    } else {
      $id = 0;
    }
    $result->close();
  }

  if( isset($id) && $id > 0 ){
    # Avoid endless loop
    if($last_id == $id){ $mysqli->close(); die("got the same id $id twice in a row?\n"); }

    # We have a scheduled campaign to send, get the list and send some email    
    $sendlist = array();
    $this_limit = 500 - $sent_today;
    $query = "SELECT DISTINCT email, ref_id FROM email_mgmt WHERE email_schedule_id = $id AND status = 'pending' LIMIT $this_limit";
    if($result = $mysqli->query($query)) {
      while($row = $result->fetch_row()) {
        $this['email'] = $row[0];
        $this['ref_id'] = $row[1];
        if( ! isset($recipients[$this['email']]) ){
          # recipients keeps track of everyone we've already mailed today so no one should get more than 1 email per day
          array_push($sendlist, $this);
          $recipients[$this['email']] = 1;
        }
      }
      $result->close();

      $query = "UPDATE email_schedule SET status = \"sending\" WHERE id = $id";
      if ($stmt = $mysqli->prepare($query)) {
        $stmt->execute();
        $stmt->close();
      }

      $list_count = count($sendlist);
      $check_sent = 0;

      $check_sent = send_mail($id, $subject, $body_text, $body_html, $sendlist, $mysqli);

      while($check_sent < 1){
        # chill and wait for mail to send...
        sleep(1);
      }

      $sent_today += $list_count;

      # see if this schedule id is done mailing and update it 
      $query = "SELECT id FROM email_mgmt WHERE email_schedule_id = $id AND status = 'pending'";
      if ($stmt = $mysqli->prepare($query)) {
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows){
          $new_status = 'pending';
          $end_time = '';
        } else {
          $new_status = 'sent';
          $end_time = ", end_time = NOW()";
        }
        $stmt->close();
      }
      $query = "UPDATE email_schedule SET status = \"$new_status\" $end_time WHERE id = $id";
      print "Sent $list_count for schedule id $id. \n Query: $query \n";
      if ($stmt = $mysqli->prepare($query)) {
        $stmt->execute();
        $stmt->close(); 
      }
    }

    $last_id = $id;

  } else {
    # nothing left to email today, bail now
    print "nothing left to email today\n";
    $mysqli->close();
    exit();
  }

  # if $sent_today is under 500, go back and get more
}

# That's it, if we made it here we theoretically sent 500, bail out now
print "*** Done sending, sent $sent_today today.\n";
$mysqli->close();
exit();

function send_mail($id, $subject, $body_text, $body_html, $sendlist, $mysqli){

  print "Starting to send\n";

  #include 'Mail.php';
  #include 'Mail/mime.php' ;

  $crlf = "\n";
  $hdrs = array(
              'From'    => 'Support@Omniherbals.com',
              'Subject' => $subject
              );

  foreach($sendlist as $this_addr){

    $addr = $this_addr['email'];
    $ref_id = $this_addr['ref_id'];

    $new_body_text = $body_text . "


----------------------------
ref id: $ref_id
";

    $mime = new Mail_mime(array('eol' => $crlf));

    $mime->setTXTBody($new_body_text);
    $mime->setHTMLBody($body_html);

    $body = $mime->get();
    $hdrs = $mime->headers($hdrs);

    $mail =& Mail::factory('mail');

    ##print "shout out to $ref_id yo!!!!\n";

    $mail->send($addr, $hdrs, $body);
    $query = "UPDATE email_mgmt SET status = 'sent', send_time = NOW() WHERE email = \"$addr\" AND email_schedule_id = $id";
    if ($stmt = $mysqli->prepare($query)) {
      $stmt->execute();
      $stmt->close();
    }

    $query = "UPDATE email_schedule SET sent_count = sent_count + 1 WHERE id = $id";
    if ($stmt = $mysqli->prepare($query)) {
      $stmt->execute();
      $stmt->close();
    }

    # wait 3 seconds before sending the next one
    sleep(3);

  }
  return 1;
}

?>

