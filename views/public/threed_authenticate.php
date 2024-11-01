<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta HTTP-EQUIV="Content-Type" content="text/html;charset=UTF-8">
    <meta HTTP-EQUIV="Cache-Control" CONTENT="no cache">
    <meta HTTP-EQUIV="Pragma" CONTENT="no cache">
    <meta HTTP-EQUIV="Expires" CONTENT="0">
    <title>Spryng Payments 3D Secure Redirect</title>
</head>
<body onload="AutoSubmitForm();">
<p>You're being redirected to a secure environment...</p>
<form name="threedForm" action="<?php echo $_GET['url'] ?>" method="POST">
    <input type="hidden" name="PaReq" value="<?php echo $_GET['pareq'] ?>">
    <input type="hidden" name="TermUrl" value="<?php echo $_GET['termURL'] ?>">
    <input type="hidden" name="MD" value="<?php echo $_GET['md'] ?>">
    <input type="submit" name="continue" value="Click here if you're not redirected automatically">
</form>
<script type="text/javascript">
    function AutoSubmitForm() {document.threedForm.submit();}
</script>
</body>
</html>
