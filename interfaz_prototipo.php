<?php
// interfaz.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🛠 ToolSoft</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #dffcf3, #f1fff8);
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background-color: #ffffff;
      border-bottom: 1px solid #ddd;
    }

    .logo {
      font-weight: bold;
      font-size: 1.5rem;
      color: #2ecc71;
    }

    nav a {
      margin-left: 20px;
      text-decoration: none;
      color: #333;
      font-weight: 500;
    }

    nav a:hover {
      color: #2ecc71;
    }

    .main {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 60px 80px;
      background-color: #f5f5f5;
      min-height: 80vh;
      flex-wrap: wrap;
    }

    .text-section {
      flex: 1;
      min-width: 300px;
      padding-right: 40px;
    }

    .text-section h1 {
      font-size: 2.8rem;
      margin-bottom: 10px;
      color: #000;
    }

    .text-section h2 {
      font-size: 1.5rem;
      font-weight: normal;
      margin-bottom: 20px;
      color: #333;
    }

    .text-section p {
      font-size: 1rem;
      color: #444;
      margin-bottom: 30px;
    }

    .btn {
      background-color: #2ecc71;
      color: white;
      border: none;
      padding: 12px 24px;
      font-size: 1rem;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .btn:hover {
      background-color: #27ae60;
    }

    .image-section {
      flex: 1;
      text-align: center;
    }

    .image-section img {
      max-width: 100%;
      width: 500px;
      height: auto;
      border-radius: 16px;
    }

    @media (max-width: 768px) {
      .main {
        flex-direction: column;
        text-align: center;
        padding: 40px 20px;
      }

      .text-section {
        padding-right: 0;
      }

      .image-section img {
        width: 90%;
        margin-top: 30px;
      }
    }
  </style>
</head>
<body>

  <header>
    <div class="logo">🛠 ToolSoft</div>
    <nav>
      <a href="interfaz.php">Inicio</a>
      <a href="#">Productos</a>
      <a href="contacto.php">Contacto</a>
    </nav>
  </header>

  <div class="main">
    <div class="text-section">
      <h1>¡Bienvenidos!</h1>
      <h2><?php echo "Soy ToolSoft"; ?></h2>
      <p><?php echo "Un lugar ideal para encontrar todo lo que necesitas. Desde las herramientas más especializadas hasta los accesorios más pequeños, tenemos una extensa gama de productos para fontanería, electricidad, pintura, y mucho más."; ?></p>
      <p><?php echo "Ya seas un profesional experimentado o un amante del bricolaje, aquí encontrarás lo que buscas a precios increíbles y con el respaldo de un equipo experto siempre dispuesto a ayudarte. No importa el tamaño de tu proyecto, en ToolSoft tenemos todo para hacer realidad tus ideas."; ?></p>
      <h3>¡Visítanos y transforma tus proyectos en éxitos!</h3>
      <a href="logeo_del_prototipo.php" class="btn">Iniciar Sesión</a>
    </div>
    <div class="image-section">
      <img src="ferre.png" alt="ToolSoft Ferretería">
    </div>
  </div>

</body>
</html>