<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nueva Solicitud de Publicidad</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }
        .header {
            background: linear-gradient(135deg, #b8860b, #daa520, #ffd700);
            padding: 30px;
            text-align: center;
            color: #3e2723;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px;
        }
        .field {
            margin-bottom: 25px;
        }
        .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #888;
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        .value {
            font-size: 16px;
            color: #222;
            line-height: 1.5;
        }
        .footer {
            background-color: #f4f4f4;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .divider {
            height: 1px;
            background-color: #eee;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nueva Solicitud</h1>
        </div>
        <div class="content">
            <div class="field">
                <span class="label">Nombre del Contacto</span>
                <div class="value">{{ $nombre }}</div>
            </div>
            <div class="field">
                <span class="label">Correo Electrónico</span>
                <div class="value"><strong>{{ $correo }}</strong></div>
            </div>
            <div class="field">
                <span class="label">Teléfono / WhatsApp</span>
                <div class="value">{{ $telefono ?? 'No proporcionado' }}</div>
            </div>
            <div class="field">
                <span class="label">Nombre del Negocio</span>
                <div class="value" style="color: #b8860b; font-weight: bold;">{{ $nombre_negocio }}</div>
            </div>
            <div class="divider"></div>
            <div class="field">
                <span class="label">Mensaje / Comentarios</span>
                <div class="value" style="font-style: italic;">"{{ $mensaje }}"</div>
            </div>
        </div>
        <div class="footer">
            Este es un mensaje automático generado por el portal Nenis.
        </div>
    </div>
</body>
</html>
