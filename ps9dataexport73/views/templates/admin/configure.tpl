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

  <button id="btnRunTests" class="btn btn-danger" style="font-weight:bold;">🔍 TESTS COMPLETOS</button>
  <button id="btnValidate" class="btn btn-default">1) Validar</button>
  <button id="btnRepair" class="btn btn-warning">2) Reparar</button>
  <button id="btnExport" class="btn btn-primary">3) Export SQL</button>
  <button id="btnExportCSV" class="btn btn-success" style="font-weight:bold;">📊 Export CSV (OFICIAL)</button>
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
  
  <div class="alert alert-info">
    <strong>ℹ️ NUEVO FORMATO RECOMENDADO:</strong><br>
    Usa <strong>"📊 Export CSV (OFICIAL)"</strong> para exportar en formato compatible con el <a href="https://docs.prestashop-project.org/v.8-documentation/user-guide/configuring-shop/advanced-parameters/import" target="_blank">importador nativo de PrestaShop</a>.<br>
    Después importa desde <strong>Parámetros Avanzados &gt; Importar</strong> en tu PS9.
  </div>

  <div id="filesList" style="margin-top:20px;"></div>
  <pre id="out" style="margin-top:15px; max-height: 400px; overflow:auto;"></pre>
</div>

<script>
(function () {
  var out = document.getElementById('out');
  var outputFiles = document.getElementById('outputFiles');
  var filesList = document.getElementById('filesList');
  var btnTests = document.getElementById('btnRunTests');
  var btnV = document.getElementById('btnValidate');
  var btnR = document.getElementById('btnRepair');
  var btnE = document.getElementById('btnExport');
  var btnCSV = document.getElementById('btnExportCSV');
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
      try { 
        const data = JSON.parse(txt);
        if (data.output) {
          // Mostrar output de tests en formato legible
          out.textContent = data.output + '\n\n' + JSON.stringify(data, null, 2);
        } else {
          out.textContent = JSON.stringify(data, null, 2);
        }
      }
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
            // Archivos SQL normales
            if (f.name.indexOf('backup_before_import_') === 0) {
              // Es un BACKUP - mostrar botón de restauración de emergencia
              html += '<button class="btn btn-xs btn-primary" onclick="downloadFile(\'' + f.name + '\')" title="Descargar">⬇ Descargar</button> ';
              html += '<button class="btn btn-xs btn-danger" onclick="emergencyRestore(\'' + f.name + '\')" title="RESTAURAR desde este backup">🚨 RESTAURAR EMERGENCIA</button> ';
              html += '<button class="btn btn-xs btn-warning" onclick="deleteFile(\'' + f.name + '\')" title="Eliminar archivo">🗑️ Borrar</button>';
            } else {
              // SQL normal para importar
              html += '<button class="btn btn-xs btn-primary" onclick="downloadFile(\'' + f.name + '\')" title="Descargar">⬇ Descargar</button> ';
              html += '<button class="btn btn-xs btn-info" onclick="validateFile(\'' + f.name + '\')" title="Validar">✓ Validar</button> ';
              html += '<button class="btn btn-xs btn-danger" onclick="importFile(\'' + f.name + '\')" title="Importar (con backup)">📥 IMPORTAR</button> ';
              html += '<button class="btn btn-xs btn-warning" onclick="deleteFile(\'' + f.name + '\')" title="Eliminar archivo">🗑️ Borrar</button>';
            }
          } else {
            html += '<button class="btn btn-xs btn-primary" onclick="downloadFile(\'' + f.name + '\')" title="Descargar">⬇ Descargar</button> ';
            html += '<button class="btn btn-xs btn-warning" onclick="deleteFile(\'' + f.name + '\')" title="Eliminar archivo">🗑️ Borrar</button>';
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

  window.deleteFile = async function(fname) {
    if (!confirm('⚠️ ¿Estás seguro de BORRAR el archivo?\\n\\n' + fname + '\\n\\nEsta acción NO se puede deshacer.')) return;
    
    out.textContent = 'Eliminando ' + fname + '...';
    
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=deleteFile&file=' + encodeURIComponent(fname));
      const data = await r.json();
      
      if (data.ok) {
        out.textContent = '✅ ' + data.message;
        loadFiles(); // Recargar lista
      } else {
        out.textContent = '❌ ERROR: ' + data.error;
      }
    } catch (e) {
      out.textContent = '❌ ERROR: ' + e.message;
    }
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

  btnTests.addEventListener('click', function(){ call('runTests'); });
  btnV.addEventListener('click', function(){ call('validate'); });
  btnR.addEventListener('click', function(){ if(confirm('¿Reparar datos?')) call('repair'); });
  btnE.addEventListener('click', function(){ call('exportSql'); });
  btnCSV.addEventListener('click', async function(){ 
    var menu = 'Selecciona qué exportar en formato CSV OFICIAL:\\n\\n';
    menu += '1 = Productos\\n';
    menu += '2 = Categorías\\n';
    menu += '3 = Marcas\\n';
    menu += '4 = Proveedores\\n';
    menu += '5 = Clientes\\n';
    menu += '6 = Direcciones';
    
    var type = prompt(menu, '1');
    if (!type) return;
    
    var exportTypes = {
      '1': 'products',
      '2': 'categories',
      '3': 'brands',
      '4': 'suppliers',
      '5': 'customers',
      '6': 'addresses'
    };
    
    var exportType = exportTypes[type] || 'products';
    var typeNames = {
      'products': 'Productos',
      'categories': 'Categorías',
      'brands': 'Marcas',
      'suppliers': 'Proveedores',
      'customers': 'Clientes',
      'addresses': 'Direcciones'
    };
    
    out.textContent = 'Exportando ' + typeNames[exportType] + ' a CSV formato oficial PrestaShop...';
    btnCSV.disabled = true;
    try {
      var p = params();
      p.set('action', 'exportCSV');
      p.set('export_type', exportType);
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&' + p.toString());
      const data = await r.json();
      out.textContent = data.ok ? '✅ CSV CREADO\\n' + JSON.stringify(data, null, 2) + '\\n\\nAhora ve a "Ver archivos" para descargarlo e importarlo en PS9 desde Parámetros Avanzados > Importar' : '❌ ERROR\\n' + JSON.stringify(data, null, 2);
      if (data.ok) alert('✅ CSV exportado: ' + data.filename + '\\n\\nVe a "Ver archivos" para descargarlo.\\nLuego importa en PS9 desde: Parámetros Avanzados > Importar');
    } catch (e) {
      out.textContent = '❌ ERROR: ' + e.message;
    } finally {
      btnCSV.disabled = false;
    }
  });
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
  
  // FUNCIÓN DE RESTAURACIÓN DE EMERGENCIA
  window.emergencyRestore = async function(filename) {
    if (!confirm('🚨 RESTAURACIÓN DE EMERGENCIA\n\nEsto REEMPLAZARÁ todas las tablas con el backup:\n' + filename + '\n\nSe perderán TODOS los cambios desde ese backup.\n\n¿Continuar?')) {
      return;
    }
    
    if (!confirm('ÚLTIMA CONFIRMACIÓN: ¿Estás SEGURO de restaurar el backup?\n\nEsta acción NO se puede deshacer.')) {
      return;
    }
    
    var out = document.getElementById('outputFiles');
    out.textContent = '⏳ Restaurando backup de emergencia... ESPERA...';
    
    try {
      const r = await fetch('{$baseUrl|escape:'javascript'}' + '&ajax=1&action=emergencyRestore&file=' + encodeURIComponent(filename));
      const data = await r.json();
      
      if (data.ok) {
        out.textContent = '✅ RESTAURACIÓN COMPLETA\n\n' + data.message + '\n\nRecarga la página para ver los cambios.';
        alert('✅ Backup restaurado correctamente.\n\nRECARGA la página ahora (F5).');
      } else {
        out.textContent = '❌ ERROR en restauración: ' + data.error;
        alert('❌ Error: ' + data.error);
      }
    } catch (e) {
      out.textContent = '❌ ERROR: ' + e.message;
      alert('❌ ERROR: ' + e.message);
    }
  };
})();
</script>
