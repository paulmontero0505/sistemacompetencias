<div class="login-shell">
  <aside class="login-brand">
    <div class="grid-motif"></div>

    <div class="lb-top">
      <span class="lb-logo"><img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="COSCO SHIPPING · Puerto de Chancay"></span>
    </div>

    <div class="lb-mid">
      <h1>Competencias y Eficiencia Operativa</h1>
      <p class="lead">Plataforma de registro y seguimiento del desempeño de los operadores de equipos del Puerto de Chancay.</p>
      <div class="lb-badges">
        <span class="lb-chip"><i class="bi bi-building"></i> ARMG</span>
        <span class="lb-chip"><i class="bi bi-crop"></i> QC / STS</span>
        <span class="lb-chip"><i class="bi bi-diagram-3"></i> Portal Crane</span>
        <span class="lb-chip"><i class="bi bi-truck-front"></i> Wheel Loader</span>
      </div>
    </div>

    <div class="lb-foot"><?= h(APP_EMPRESA) ?></div>
  </aside>

  <main class="login-form-side">
    <div class="login-panel">
      <div class="lp-logo"><img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="COSCO SHIPPING · Puerto de Chancay"></div>

      <h2>Bienvenido</h2>
      <p class="lp-sub">Ingresa tus credenciales para acceder al sistema.</p>

      <form method="post" action="?action=login">
        <div class="form-floating mb-2">
          <input type="email" name="email" class="form-control" id="femail" placeholder="correo" required autofocus>
          <label for="femail"><i class="bi bi-envelope"></i> Correo</label>
        </div>
        <div class="form-floating mb-3">
          <input type="password" name="password" class="form-control" id="fpass" placeholder="clave" required>
          <label for="fpass"><i class="bi bi-lock"></i> Contraseña</label>
        </div>
        <button class="login-btn"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
      </form>
    </div>
  </main>
</div>
