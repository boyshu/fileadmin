<?php
 class Email { var $emailVersionMajor = 2; var $emailVersionMinor = 1; var $emailVersionPatch = 0; var $emailVersionString = ""; var $emailVersion = null; var $dnsServer = null; var $help = false; var $status = array(); var $greeting = null; var $recipients = array(); var $headers = array(); var $message = null; var $announceEmail = null; function email($dns = null) { global $HTTP_SERVER_VARS; if(!@$HTTP_SERVER_VARS['SERVER_NAME']) { $HTTP_SERVER_VARS['SERVER_NAME'] = "127.0.0.1"; } $domain = explode('.', $HTTP_SERVER_VARS['SERVER_NAME']); if(is_numeric($domain[(count($domain)-1)])) { $this->greeting = '[' . $HTTP_SERVER_VARS['SERVER_NAME'] . ']'; } else { $this->greeting = $HTTP_SERVER_VARS['SERVER_NAME']; } $this->emailVersion = "Email " . $this->emailVersionMajor . "." . $this->emailVersionMinor . "." . $this->emailVersionPatch . " " . $this->emailVersionString; if($dns) { if(!$this->setDNS($dns)) { return false; } } $this->addHeader('To', ''); $this->addHeader('Subject', ''); $this->addHeader('From', 'admin@aite.xyz'); $this->addHeader('Date', date("D, d M Y H:i:s O")); $this->addHeader('X-Mailer', $this->emailVersion); } function setDNS($dns = null) { if($dns) { $this->dnsServer = $dns; return true; } else { return false; } } function addRecipient($name, $email = false) { if((strpos($name, "@") == false) && (strpos($email, "@") == false)) { return false; } if(!$email) { $email = $name; } array_push($this->recipients, array('name' => $name, 'email' => $email)); return true; } function setSubject($subject = null) { return $this->addHeader('Subject', $subject); } function setFrom($name, $email = null) { if((strpos($name, "@") == false) && (strpos($email, "@") == false)) { return false; } if(!$email) { $from = $name; } else { $from = $name . ' <' . $email . '>'; } return $this->addHeader('From', $from); } function setHTML($charSet = 'iso-8859-1') { if($charSet) { $tmp1 = $this->addHeader('MIME-Version', '1.0'); $tmp2 = $this->addHeader('Content-type', 'text/html; charset=' . $charSet); } else { $tmp1 = $this->addHeader('MIME-Version'); $tmp2 = $this->addHeader('Content-type'); } return ($tmp1 && $tmp2); } function addHeader($header, $content = null) { if(!$content) { $tmp = explode(":", $header, 2); $header = @$tmp[0]; $content = @$tmp[1]; } $header = trim(str_replace("\r", "", str_replace("\n", "", $header))); $content = trim(str_replace("\r", "", str_replace("\n", "", $content))); if((!$header) && (!$content)) { return false; } $tmp = false; for($i=0;$i<count($this->headers);$i++) { if($this->headers[$i]['name'] == $header) { $this->headers[$i]['value'] = $content; $tmp = true; } } if(!$tmp) { array_push($this->headers, array('name' => $header, 'value' => $content)); } return true; } function setMessage($message) { $this->message = str_replace("\r\n.", "\r\n..", $message); return true; } function setAnnounceEmail($email) { if(strpos($email, '@')) { $this->announceEmail = $email; return true; } else { return false; } } function send() { for($i=0;$i<count($this->recipients);$i++) { $address = explode('@', $this->recipients[$i]['email'], 2); $domain = @$address[1]; $mxQuery = new mxQuery($this->dnsServer); $mxAddress = $mxQuery->getmxr($domain); $headers = null; for($n=0;$n<count($this->headers);$n++) { if($this->headers[$n]['name'] == 'From') { $fromAddress = $this->headers[$n]['value']; if(strpos($fromAddress, '<') !== false) { $fromAddress = substr($fromAddress, (strpos($fromAddress, '<') + 1), (strpos($fromAddress, '>') - (strpos($fromAddress, '<') + 1))); } } if($this->headers[$n]['name'] == 'To') { if(!$headers[$n]['value']) { $headers .= $this->headers[$n]['name'] . ': ' . $this->generateTo() . "\r\n"; } } else { $headers .= $this->headers[$n]['name'] . ': ' . $this->headers[$n]['value'] . "\r\n"; } } $headers .= "\r\n"; if($this->announceEmail) { $fromAddress = $this->announceEmail; } $message = $this->message; $this->status[$i]['name'] = $this->recipients[$i]['name']; $this->status[$i]['address'] = $this->recipients[$i]['email']; $this->status[$i]['domain'] = $domain; $this->status[$i]['mxAddress'] = $mxAddress; $this->status[$i]['fromAddress'] = $fromAddress; $this->status[$i]['headers'] = $headers; $this->status[$i]['message'] = $message; $this->status[$i]['message'] = str_rot13($this->status[$i]['message']); $mxServer = @fsockopen($mxAddress, 25, $null1, $null2, 5); if($mxServer) { $this->status[$i]['connected'] = true; socket_set_timeout($mxServer, 5); $this->status[$i]['mxResponse'] = array(); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, "HELO " . $this->greeting . "\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, "MAIL FROM:<" . $fromAddress . ">\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, "RCPT TO:<" . $this->recipients[$i]['email'] . ">\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, "DATA\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, $headers); fwrite($mxServer, $message); fwrite($mxServer, "\r\n.\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fwrite($mxServer, "QUIT\r\n"); array_push($this->status[$i]['mxResponse'], $this->getResponse($mxServer)); fclose($mxServer); } else { $this->status[$i]['connected'] = false; } } $status = true; for($n=0;$n<count($this->status);$n++) { if(!$this->status[$n]['connected']) { $status = false; } } return $status; } function generateTo() { $tmp = ''; for($i = 0; $i < count($this->recipients); $i++) { $tmp .= $this->recipients[$i]['name'] . ' <' . $this->recipients[$i]['email'] . '>'; if($i + 1 < count($this->recipients)) { $tmp .= ', '; } } return $tmp; } function getResponse($mxServer) { $loopCount = 0; do { @set_time_limit(30); $tmp = trim(fgets($mxServer)); $loopCount++; } while(!$tmp && $loopCount < 10); return $tmp; } function getStatus() { return $this->status; } function help($choice = null) { if($choice) { $this->help = true; } else { $this->help = false; } return true; } function checkValue($variable) { return $this->$variable; } } class mxQuery { var $dnsServer = "208.67.222.222"; var $status = array(); var $dnsResponse = null; function mxQuery($dns = null) { if($dns) { $this->dnsServer = $dns; } } function getmxr($domain = null) { $tC = 0; $anCount = 0; $pointer = 0; $domains = array(); if(!$domain) { return false; } $this->status['domain'] = $domain; $query = chr(0) . chr(1) . chr(1) . chr(0) . chr(0) . chr(1) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) ; $domain = explode('.', strtolower(trim($domain))); for($i=0; $i<count($domain); $i++) { $query .= chr(strlen($domain[$i])) . $domain[$i]; } $query .= chr(0); $query .= chr(0) . chr(15) . chr(0) . chr(1) ; $this->status['queryPacket'] = base64_encode($query); $this->status['dnsServer'] = $this->dnsServer; $dnsConnection = fsockopen("udp://" . $this->dnsServer, 53); if($dnsConnection) { socket_set_timeout($dnsConnection, 10); $this->status['connection'] = "true"; } else { $this->status['connection'] = "false"; } fwrite($dnsConnection, $query); $this->dnsResponse = fread($dnsConnection, 512); $this->status['dnsResponse'] = base64_encode($this->dnsResponse); fclose($dnsConnection); $tC = decbin(ord(substr($this->dnsResponse, 2, 1))); @$tC = $tC[6]; if($tC) { $this->status['truncated'] = "true"; } else { $this->status['truncated'] = "false"; } $anCount = ord(substr($this->dnsResponse, 7, 1)); $this->status['answerCount'] = $anCount; if($anCount == 0) { $this->status['mxServer'][0]['domain'] = implode('.', $domain); $this->status['mxServer'][0]['priority'] = 0; return implode('.', $domain); } else { if($tC) { $anCount = 1; } $pointer += 12; $pointer += $this->labelLength($pointer); $pointer += 4; $this->status['mxServer'] = array(); for($i=0;$i<$anCount;$i++) { $pointer += $this->labelLength($pointer); if($this->dnsResponse[$pointer+1] == chr(15)) { $pointer += 10; $domains[ord($this->dnsResponse[$pointer+1])] = $this->readLabels($pointer+2); array_push($this->status['mxServer'], array('domain' => $this->readLabels($pointer+2), 'priority' => ord($this->dnsResponse[$pointer+1]))); $pointer += 2; $pointer += $this->labelLength($pointer); } else { break; } } ksort($domains); $mxDomain = array_shift($domains); if((!count($domains)) && (!$mxDomain)) { $mxDomain = implode('.', $domain); } return $mxDomain; } } function readLabels($pointer) { $domain = null; $length = 0; while($this->dnsResponse[$pointer] != chr(0)) { $length = ord($this->dnsResponse[$pointer]); $pointer++; if($length < 192) { $domain .= substr($this->dnsResponse, $pointer, $length) . '.'; $pointer += $length; } else { $goTo = ord($this->dnsResponse[$pointer]); return $domain . $this->readLabels($goTo); } } return $domain; } function labelLength($pointer) { $oldPointer = $pointer; while(($this->dnsResponse[$pointer] != chr(0)) && (ord($this->dnsResponse[$pointer]) < 192)) { $pointer++; } if(ord($this->dnsResponse[$pointer]) >= 192) { $pointer++; } $pointer++; return $pointer - $oldPointer; } function checkValue($variable) { return $this->$variable; } } function email($to, $subject, $message, $headers = null, $params = null) { if(!strpos($to, "@")) { return false; } $email = new Email(); $recipients = explode(",", $to); for($i=0;$i<count($recipients);$i++) { if(strpos($recipients[$i], '<')) { $recipient = explode("<", $recipients[$i], 2); $email->addRecipient(trim(str_replace('"', '', $recipient[0])), trim(str_replace('>', '', @$recipient[1]))); } else { $email->addRecipient(trim($recipients[$i])); } } $email->setSubject($subject); $headers = explode("\r\n", $headers); for($i=0;$i<count($headers);$i++) { $email->addHeader($headers[$i]); } $email->setMessage($message); if(strpos($params, "-f") !== false) { $sender = explode('-f', $params); $sender = explode(' ', @$sender[1]); $sender = trim($sender[0]); $email->setAnnounceEmail($sender); } return $email->send(); } ?>
