<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de correo electrónico</title>
</head>
<body>
<div>
    <h2>Verificación de correo electrónico</h2>

    <br>
    <p>Hola {{ $nombre }}, te has registrado en la plataforma de NENIS</p>
    <br>
    <p>Por favor, verifica tu correo electrónico <a href="{{ $url }}">aquí</a> para que puedas continuar con el registro.</p>
    <br>
    <b>** Este correo es automático, no respondas a este correo **</b>
</div>
</body>
</html>