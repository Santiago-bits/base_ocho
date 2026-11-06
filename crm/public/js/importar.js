/*
 * Importación de Excels: se lee el archivo con SheetJS en el navegador,
 * se mapea cada columna a un campo y se mandan las filas como JSON.
 */
(function () {
  'use strict';

  const config = JSON.parse(document.getElementById('config-import').textContent);
  const $ = (sel) => document.querySelector(sel);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  const estado = {
    libro: null,
    archivo: '',
    encabezados: [],
    filas: [],        // filas crudas del Excel (arrays)
    filaInicio: 0,    // número de fila (1-based) del encabezado
    mapeo: [],        // campo elegido por columna
    mapeoEstados: {}, // valor normalizado de "Estado envío" => estado del sistema
    payload: [],
    analisis: [],
    decisiones: {},
  };

  // ---------- Utilidades ----------
  const normalizar = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const celda = (v) => {
    if (v === null || v === undefined) return '';
    if (typeof v === 'number') return Number.isInteger(v) ? String(v) : String(v);
    if (v instanceof Date) return v.toLocaleDateString('es-AR');
    return String(v).trim();
  };
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Los CSV pueden venir en UTF-8 o en Windows-1252 (Excel en Windows).
  function decodificarTexto(buffer) {
    try {
      return new TextDecoder('utf-8', { fatal: true }).decode(buffer).replace(/^﻿/, '');
    } catch (e) {
      return new TextDecoder('windows-1252').decode(buffer);
    }
  }

  function mostrarError(msg) {
    const box = $('#error-import');
    box.innerHTML = msg;
    box.hidden = !msg;
    if (msg) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function irAPaso(n) {
    document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = Number(p.dataset.panel) !== n; });
    document.querySelectorAll('#pasos [data-paso]').forEach((p) => p.classList.toggle('activo', Number(p.dataset.paso) === n));
    mostrarError('');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function postJSON(url, datos) {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(datos),
      credentials: 'same-origin',
    });
    if (resp.status === 419) throw new Error('La sesión expiró. Recargá la página y volvé a intentar.');
    const json = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      const errores = json.errors ? Object.values(json.errors).flat().join('<br>') : (json.message || 'Error del servidor.');
      throw new Error(errores);
    }
    return json;
  }

  // ---------- Adivinar campos ----------
  const REGLAS = [
    ['estado_envio', ['estado', 'envio', 'enviado']],
    ['whatsapp', ['whatsapp', 'whats', 'wsp', 'wpp', 'wa']],
    ['email', ['email', 'e-mail', 'mail', 'correo']],
    ['telefono', ['telefono', 'tel', 'celular', 'cel', 'movil', 'fijo']],
    ['ciudad', ['ciudad', 'localidad', 'pueblo', 'loc']],
    ['direccion', ['direccion', 'domicilio', 'calle', 'ubicacion']],
    ['web_redes', ['web', 'pagina', 'sitio', 'instagram', 'facebook', 'redes', 'url', 'link']],
    ['fuente', ['fuente', 'origen']],
    ['notas', ['nota', 'observ', 'comentario']],
    ['nombre', ['nombre', 'empresa', 'razon social', 'negocio', 'comercio', 'inmobiliaria', 'concesionaria', 'cliente']],
  ];

  function adivinarCampo(encabezado, usados) {
    const h = normalizar(encabezado);
    if (!h) return '';
    for (const [campo, claves] of REGLAS) {
      const coincide = claves.some((k) => (k.length <= 3 ? h.split(/[^a-z]+/).includes(k) : h.includes(k)));
      if (coincide && (campo === 'notas' || !usados.has(campo))) return campo;
    }
    return '';
  }

  function adivinarEstado(texto) {
    const t = normalizar(texto);
    if (!t) return 'sin_contactar';
    if (/no (contactar|molestar|llamar|escribir)/.test(t)) return 'no_contactar';
    if (t.includes('cliente')) return 'cliente';
    if (/perdid|no (le )?interes|rechaz|no quiere/.test(t)) return 'perdido';
    if (t.includes('interes')) return 'interesado';
    if (/respond|contest/.test(t)) return 'respondio';
    if (/no envi|sin envi|pendiente|falta|no mand/.test(t)) return 'sin_contactar';
    if (/envi|mand|contactad|escrib|si\b/.test(t)) return 'contactado';
    return 'sin_contactar';
  }

  // ---------- Paso 1: leer archivo ----------
  $('#rubro').addEventListener('change', (e) => {
    $('#rubro-nuevo-wrap').hidden = e.target.value !== 'nuevo';
    validarPaso1();
  });
  $('#rubro-nuevo').addEventListener('input', validarPaso1);

  function validarPaso1() {
    const rubro = $('#rubro').value;
    const okRubro = rubro && (rubro !== 'nuevo' || $('#rubro-nuevo').value.trim());
    $('#ir-paso-2').disabled = !(okRubro && estado.filas.length);
  }

  $('#archivo').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    mostrarError('');
    try {
      const datos = await file.arrayBuffer();
      estado.libro = /\.(csv|txt)$/i.test(file.name)
        ? XLSX.read(decodificarTexto(datos), { type: 'string', raw: true })
        : XLSX.read(datos, { type: 'array', cellDates: true });
      estado.archivo = file.name;
      const hojas = estado.libro.SheetNames;
      const sel = $('#hoja');
      sel.innerHTML = hojas.map((h) => `<option>${esc(h)}</option>`).join('');
      $('#hoja-wrap').hidden = hojas.length < 2;
      leerHoja(hojas[0]);
    } catch (err) {
      mostrarError('No pude leer el archivo. ¿Es un Excel o CSV válido?');
      estado.filas = [];
      validarPaso1();
    }
  });
  $('#hoja').addEventListener('change', (e) => leerHoja(e.target.value));

  function leerHoja(nombre) {
    const hoja = estado.libro.Sheets[nombre];
    const matriz = XLSX.utils.sheet_to_json(hoja, { header: 1, raw: true, defval: '', blankrows: false });
    // El encabezado es la primera fila con al menos 2 celdas con texto.
    let idx = matriz.findIndex((fila) => fila.filter((c) => celda(c) !== '').length >= 2);
    if (idx < 0) idx = 0;
    const encabezados = (matriz[idx] || []).map((c, i) => celda(c) || `Columna ${i + 1}`);
    estado.encabezados = encabezados;
    estado.filaInicio = idx + 1;
    estado.filas = matriz.slice(idx + 1)
      .map((fila, i) => ({ numero: idx + 2 + i, celdas: fila }))
      .filter((f) => f.celdas.some((c) => celda(c) !== ''));
    $('#info-archivo').textContent = estado.filas.length
      ? `${estado.archivo}: ${estado.filas.length} filas con datos y ${encabezados.length} columnas.`
      : 'La hoja no tiene filas con datos.';
    validarPaso1();
  }

  $('#ir-paso-2').addEventListener('click', () => {
    armarMapeo();
    irAPaso(2);
  });

  // ---------- Paso 2: mapeo ----------
  function armarMapeo() {
    const usados = new Set();
    estado.mapeo = estado.encabezados.map((h) => {
      const campo = adivinarCampo(h, usados);
      if (campo) usados.add(campo);
      return campo;
    });

    const opciones = (sel) => ['<option value="">No importar</option>']
      .concat(Object.entries(config.campos).map(([v, t]) => `<option value="${v}" ${v === sel ? 'selected' : ''}>${esc(t)}</option>`))
      .join('');

    $('#mapeo').innerHTML = estado.encabezados.map((h, i) => {
      const ejemplos = estado.filas.map((f) => celda(f.celdas[i])).filter(Boolean).slice(0, 3);
      return `<tr>
        <td class="principal"><strong>${esc(h)}</strong></td>
        <td data-label="Ejemplos" class="ejemplos">${ejemplos.map(esc).join(' · ') || '—'}</td>
        <td data-label="Campo"><select data-col="${i}">${opciones(estado.mapeo[i])}</select></td>
      </tr>`;
    }).join('');

    $('#mapeo').querySelectorAll('select').forEach((s) => s.addEventListener('change', () => {
      estado.mapeo[Number(s.dataset.col)] = s.value;
      armarMapeoEstados();
    }));
    armarMapeoEstados();
  }

  function armarMapeoEstados() {
    const col = estado.mapeo.indexOf('estado_envio');
    $('#estados-wrap').hidden = col < 0;
    if (col < 0) return;

    const conteo = new Map();
    estado.filas.forEach((f) => {
      const original = celda(f.celdas[col]);
      const clave = normalizar(original);
      if (!conteo.has(clave)) conteo.set(clave, { original: original || '(vacío)', n: 0 });
      conteo.get(clave).n++;
    });

    const filas = [...conteo.entries()].sort((a, b) => b[1].n - a[1].n);
    filas.forEach(([clave, info]) => {
      if (!(clave in estado.mapeoEstados)) estado.mapeoEstados[clave] = adivinarEstado(info.original === '(vacío)' ? '' : info.original);
    });

    $('#mapeo-estados').innerHTML = filas.map(([clave, info]) => `<tr>
        <td class="principal">${esc(info.original)}</td>
        <td data-label="Filas" class="num">${info.n}</td>
        <td data-label="Estado"><select data-estado="${esc(clave)}">${Object.entries(config.estados)
          .map(([v, t]) => `<option value="${v}" ${estado.mapeoEstados[clave] === v ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></td>
      </tr>`).join('');

    $('#mapeo-estados').querySelectorAll('select').forEach((s) => s.addEventListener('change', () => {
      estado.mapeoEstados[s.dataset.estado] = s.value;
    }));
  }

  function armarPayload() {
    const colEstado = estado.mapeo.indexOf('estado_envio');
    return estado.filas.map((f) => {
      const fila = { _fila: f.numero };
      const extras = [];
      estado.mapeo.forEach((campo, i) => {
        const valor = celda(f.celdas[i]);
        if (!campo || !valor) return;
        if (campo === 'notas') {
          fila.notas = fila.notas ? `${fila.notas} | ${valor}` : valor;
        } else if (fila[campo]) {
          extras.push(`${estado.encabezados[i]}: ${valor}`);
        } else {
          fila[campo] = valor;
        }
      });
      if (extras.length) fila.notas = [fila.notas, ...extras].filter(Boolean).join(' | ');
      fila.estado = colEstado >= 0 ? (estado.mapeoEstados[normalizar(celda(f.celdas[colEstado]))] || 'sin_contactar') : 'sin_contactar';
      return fila;
    });
  }

  $('#ir-paso-3').addEventListener('click', async () => {
    if (!estado.mapeo.includes('nombre')) {
      mostrarError('Elegí qué columna tiene el <strong>Nombre</strong> de la empresa.');
      return;
    }
    const boton = $('#ir-paso-3');
    boton.disabled = true;
    boton.textContent = 'Revisando…';
    try {
      estado.payload = armarPayload();
      const resp = await postJSON(config.urls.revisar, { filas: estado.payload });
      estado.analisis = resp.analisis;
      estado.decisiones = {};
      armarRevision(resp.resumen);
      irAPaso(3);
    } catch (err) {
      mostrarError(err.message);
    } finally {
      boton.disabled = false;
      boton.textContent = 'Siguiente: revisar duplicados';
    }
  });

  // ---------- Paso 3: revisión ----------
  function armarRevision(resumen) {
    const pastilla = (clase, texto) => `<span class="pastilla ${clase}">${texto}</span>`;
    $('#resumen').innerHTML = [
      pastilla('ok', `${resumen.nueva} nuevas`),
      pastilla('aviso', `${resumen.duplicada} ya existen`),
      resumen.repetida ? pastilla('', `${resumen.repetida} repetidas en el archivo`) : '',
      resumen.invalida ? pastilla('error', `${resumen.invalida} sin nombre`) : '',
    ].join(' ');

    const dups = [];
    const otras = [];
    const nuevas = [];
    estado.analisis.forEach((a, i) => {
      const f = estado.payload[i];
      if (a.tipo === 'duplicada') dups.push([a, f, i]);
      else if (a.tipo === 'nueva') nuevas.push(f);
      else otras.push([a, f]);
    });

    $('#duplicadas-wrap').hidden = !dups.length;
    $('#duplicadas').innerHTML = dups.map(([a, f, i]) => {
      const c = a.coincidencia;
      return `<tr>
        <td class="principal"><strong>${esc(f.nombre)}</strong><div class="muted chico">Fila ${f._fila} · ${esc(a.motivo)}</div></td>
        <td data-label="Ya cargada como"><a href="${config.urls.empresas}/${c.id}" target="_blank" rel="noopener">${esc(c.nombre)}</a>
          <div class="muted chico">${esc([c.rubro, c.ciudad, c.estado].filter(Boolean).join(' · '))}</div></td>
        <td data-label="Qué hago"><span class="chips">
          <label class="chip"><input type="radio" name="dec-${i}" value="actualizar" data-i="${i}"> Actualizar</label>
          <label class="chip"><input type="radio" name="dec-${i}" value="saltear" data-i="${i}" checked> Saltear</label>
        </span></td>
      </tr>`;
    }).join('');
    $('#duplicadas').querySelectorAll('input[type=radio]').forEach((r) => r.addEventListener('change', () => {
      estado.decisiones[r.dataset.i] = r.value;
      actualizarBotonConfirmar();
    }));

    $('#otras-wrap').hidden = !otras.length;
    $('#otras').innerHTML = otras.map(([a, f]) => `<li><span>Fila ${f._fila}: ${esc(f.nombre || '(sin nombre)')}</span><span class="muted chico">${esc(a.motivo)}</span></li>`).join('');

    $('#nuevas-wrap').hidden = !nuevas.length;
    $('#nuevas').innerHTML = nuevas.slice(0, 200).map((f) => `<li><span>${esc(f.nombre)}<span class="sub">${esc([f.ciudad, f.telefono || f.whatsapp, f.email].filter(Boolean).join(' · '))}</span></span><span class="muted chico">${esc(config.estados[f.estado])}</span></li>`).join('')
      + (nuevas.length > 200 ? `<li class="muted">… y ${nuevas.length - 200} más</li>` : '');

    actualizarBotonConfirmar();
  }

  function actualizarBotonConfirmar() {
    const nuevas = estado.analisis.filter((a) => a.tipo === 'nueva').length;
    const actualizar = Object.values(estado.decisiones).filter((d) => d === 'actualizar').length;
    const boton = $('#confirmar');
    boton.textContent = `Importar ${nuevas} nuevas${actualizar ? ` y actualizar ${actualizar}` : ''}`;
    boton.disabled = nuevas + actualizar === 0;
  }

  document.querySelectorAll('[data-todas]').forEach((b) => b.addEventListener('click', () => {
    $('#duplicadas').querySelectorAll(`input[value="${b.dataset.todas}"]`).forEach((r) => {
      r.checked = true;
      estado.decisiones[r.dataset.i] = r.value;
    });
    actualizarBotonConfirmar();
  }));

  document.querySelectorAll('[data-volver]').forEach((b) => b.addEventListener('click', () => irAPaso(Number(b.dataset.volver))));

  // ---------- Paso 4: confirmar ----------
  $('#confirmar').addEventListener('click', async () => {
    const boton = $('#confirmar');
    boton.disabled = true;
    boton.textContent = 'Importando…';
    const rubro = $('#rubro').value;
    try {
      const resp = await postJSON(config.urls.confirmar, {
        rubro_id: rubro === 'nuevo' ? null : Number(rubro),
        rubro_nuevo: rubro === 'nuevo' ? $('#rubro-nuevo').value.trim() : null,
        archivo: estado.archivo,
        filas: estado.payload,
        decisiones: estado.decisiones,
      });
      const r = resp.resumen;
      $('#resultado').innerHTML = `<p><strong>${r.nuevas}</strong> empresas nuevas, <strong>${r.actualizadas}</strong> actualizadas y <strong>${r.salteadas}</strong> salteadas (de ${r.filas} filas).</p>`;
      $('#ver-importadas').href = resp.url;
      irAPaso(4);
    } catch (err) {
      mostrarError(err.message);
      actualizarBotonConfirmar();
    }
  });
})();
