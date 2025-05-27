<?php
function sendCustomerEmail($to, $subject, $bodyHtml) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Courtney's Cookies <orders@ordermycookies.com>\r\n";

    return mail($to, $subject, $bodyHtml, $headers);
}
?>
