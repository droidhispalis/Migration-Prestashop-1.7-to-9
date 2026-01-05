<div class="panel">
  <h3>PS 1.7 → PS 9 Export + Import (PHP 7.3+)</h3>

  <p>
    <strong>EXPORTAR:</strong> 1) Validar → 2) Reparar → 3) Export SQL → 4) Export Imágenes<br>
    <strong>IMPORTAR:</strong> 5) Ver archivos → 6) Validar SQL → 7) IMPORTAR (con backup + rollback automático)
  </p>

  <div class="form-group">
    <label>Shop ID</label>
    <input id="shop_id" class="form-control" type="number" min="1" value="{$defaultShopId|intval}">
  </div>

  <div class="form-group">
    <label>Lang ID</label>
    <input id="lang_id" class="form-control" type="number" min="1" value="{$defaultLangId|intval}">
  </div>

  <div class="form-group">
    <label>Exportar</label>
    <div class="checkbox"><label><input type="checkbox" id="opt_catalog" checked> Catálogo</label></div>
    <div class="checkbox"><label><input type="checkbox" id="opt_customers"> Clientes</label></div>
    <div class="checkbox"><label><input type="checkbox" id="opt_orders"> Pedidos</label></div>
  </div>

  <div class="form-group">
    <label>Imágenes</label>
    <div class="checkbox"><label><input type="checkbox" id="img_prod" checked> Productos</label></div>
    <div class="checkbox"><label><input type="checkbox" id="img_cat"> Categorías</label></div>
    <div class="checkbox"><label><input type="checkbox" id="img_manu"> Fabricantes</label></div>
    <div class="checkbox"><label><input type="checkbox" id="img_supp"> Proveedores</label></div>
  </div>

  <hr>

  <button id="btnValidate" class="btn btn-default">1) Validar</button>
  <button id="btnRepair" class="btn btn-warning">2) Reparar</button>
  <button id="btnExport" class="btn btn-primary">3) Export SQL</button>
  <button id="btnImages" class="btn btn-info">4) Export IMG</button>
  <button id="btnListFiles" class="btn btn-success">5) Ver archivos</button>

  <hr>

  <div class="form-group">
    <label><strong>IMPORTAR desde archivo local:</strong></label>
    <div>
      <input type="file" id="sqlFileInput" accept=".sql" class="form-control" style="display:inline-block; width:auto;">
      <button id="btnUpload" class="btn btn-primary">Subir archivo SQL</button>
    </div>
    <p class="help-block">Selecciona un archivo .sql de tu computadora para subirlo y luego importarlo</p>
  </div>

  <div id="filesList" style="margin-top:20px;"></div>
  <pre id="out" style="margin-top:15px; max-height: 400px; overflow:auto;"></pre>
</div>

<script>
(function () {
  var out = document.getElementById('out');
  var filesList = document.getElementById('filesList');
  var btnV = document.getElementById('btnValidate');
  var btnR = document.getElementById('btnRepair');
  var btnE = document.getElementById('btnExport');
  var btnI = document.getElementById('btnImages');
  var btnList = document.getElementById('btnListFiles');
  var btnUpload = document.getElementById('btnUpload');
  var sqlFileInput = document.getElementById('sqlFileInput');

  function params() {
    return new URLSearchParams({
      ajax: '1',
      shop_id: document.getElementById('shop_id').value || '{$defaultShopId|intval}',
      lang_id: document.getElementById('lang_id').value || '{$defaultLangId|intval}',
      catalog: document.getElementById('opt_catalog').checked ? '1' : '0',
      customers: document.getElementById('opt_customers').checked ? '1' : '0',
      orders: document.getElementById('opt_orders').checked ? '1' : '0',
      img_prod: document.getElementById('img_prod').checked ? '1' : '0',
      img_cat: document.getElementById('img_cat').checked ? '1' : '0',
      img_manu: document.getElementById('img_manu').checked ? '1' : '0',
      img_supp: document.getElementById('img_supp').checked ? '1' : '0'
    });
  }

  async function call(action) {
    out.textContent = 'Procesando...';
    var p = params();
    p.set('action', action);
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&' + p.toString());
      const txt = await r.text();
      try { out.textContent = JSON.stringify(JSON.parse(txt), null, 2); }
      catch (e) { out.textContent = txt; }
    } catch (e) {
      out.textContent = 'ERROR: ' + e.message;
    }
  }

  async function loadFiles() {
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=listFiles');
      const data = await r.json();
      
      if (data.ok && data.files && data.files.length) {
        var html = '<table class="table table-sm"><tr><th>Archivo</th><th>KB</th><th>Fecha</th><th>Acciones</th></tr>';
        data.files.forEach(function(f) {
          var kb = Math.round(f.size / 1024);
          html += '<tr><td>' + f.name + '</td><td>' + kb + '</td><td>' + f.date + '</td><td>';
          html += '<button class="btn btn-xs btn-success" onclick="downloadFile(\'' + f.name + '\')">Descargar</button> ';
          if (f.type === 'sql') {
            html += '<button class="btn btn-xs btn-info" onclick="validateFile(\'' + f.name + '\')">Validar</button> ';
            html += '<button class="btn btn-xs btn-danger" onclick="importFile(\'' + f.name + '\')">IMPORTAR</button>';
          }
          html += '</td></tr>';
        });
        html += '</table><p class="help-block">También disponible por FTP: /download/ps9-export/</p>';
        filesList.innerHTML = html;
      } else {
        filesList.innerHTML = '<p>No hay archivos.</p>';
      }
    } catch (e) {
      filesList.innerHTML = '<p>Error: ' + e.message + '</p>';
    }
  }

  window.validateFile = async function(fname) {
    out.textContent = 'Validando ' + fname + '...';
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=validateImport&file=' + fname);
      const data = await r.json();
      out.textContent = JSON.stringify(data, null, 2);
    } catch (e) {
      out.textContent = 'ERROR: ' + e.message;
    }
  };

  window.downloadFile = function(fname) {
    // Construir URL de descarga con parámetro especial que se procesa en __construct()
    var downloadUrl = '{$baseUrl|escape:'javascript'}' + '&downloadPs9Export=1&file=' + encodeURIComponent(fname);
    
    // Redirigir directamente - el módulo lo captura antes de generar HTML
    window.location.href = downloadUrl;
  };

  window.importFile = async function(fname) {
    if (!confirm('⚠️ IMPORTAR reemplazará datos.\\n✅ Se creará BACKUP automático\\n✅ ROLLBACK si hay error\\n\\n¿Continuar?')) return;
    
    out.textContent = 'IMPORTANDO... puede tardar varios minutos...\\nCreando backup...';
    btnV.disabled = btnR.disabled = btnE.disabled = btnI.disabled = btnList.disabled = true;
    
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=import&file=' + fname);
      const data = await r.json();
      out.textContent = data.ok ? '✅ ÉXITO\\n' + JSON.stringify(data, null, 2) : '❌ ERROR\\n' + JSON.stringify(data, null, 2);
      if (data.ok) alert('✅ Importación exitosa!');
    } catch (e) {
      out.textContent = '❌ ERROR: ' + e.message;
    } finally {
      btnV.disabled = btnR.disabled = btnE.disabled = btnI.disabled = btnList.disabled = false;
    }
  };

  btnV.addEventListener('click', function(){ call('validate'); });
  btnR.addEventListener('click', function(){ if(confirm('¿Reparar datos?')) call('repair'); });
  btnE.addEventListener('click', function(){ call('exportSql'); });
  btnI.addEventListener('click', function(){ call('exportImages'); });
  btnList.addEventListener('click', loadFiles);
  
  btnUpload.addEventListener('click', async function() {
    if (!sqlFileInput.files || !sqlFileInput.files[0]) {
      alert('Selecciona un archivo .sql primero');
      return;
    }
    
    var file = sqlFileInput.files[0];
    if (!file.name.toLowerCase().endsWith('.sql')) {
      alert('Solo se permiten archivos .sql');
      return;
    }
    
    out.textContent = 'Subiendo archivo...';
    btnUpload.disabled = true;
    
    try {
      var formData = new FormData();
      formData.append('sqlfile', file);
      
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=uploadFile', {
        method: 'POST',
        body: formData
      });
      
      const data = await r.json();
      
      if (data.ok) {
        out.textContent = '✅ Archivo subido: ' + data.filename;
        sqlFileInput.value = '';
        alert('✅ Archivo subido correctamente. Haz clic en "Ver archivos" para verlo.');
        loadFiles();
      } else {
        out.textContent = '❌ Error: ' + data.error;
      }
    } catch (e) {
      out.textContent = '❌ ERROR: ' + e.message;
    } finally {
      btnUpload.disabled = false;
    }
  });
})();
</script>
