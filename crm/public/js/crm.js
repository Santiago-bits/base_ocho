/* CRM BaseOcho — comportamiento general (sin dependencias). */
(function () {
  'use strict';

  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const hoyISO = (sumarDias = 0) => {
    const d = new Date();
    d.setDate(d.getDate() + sumarDias);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  };

  // Confirmación antes de borrar.
  $$('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
  });

  // Selects que guardan al cambiar.
  $$('select[data-autosubmit]').forEach((sel) => sel.addEventListener('change', () => sel.form.submit()));

  // Atajos de fecha: mañana, en 3 días, próximo lunes…
  $$('[data-fecha-rapida]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const destino = document.querySelector(btn.dataset.destino);
      const valor = btn.dataset.fechaRapida;
      let dias = Number(valor);
      if (valor === 'lunes') {
        const hoy = new Date().getDay(); // 0 = domingo
        dias = ((8 - hoy) % 7) || 7;
      }
      destino.value = hoyISO(dias);
      destino.dispatchEvent(new Event('change'));
    });
  });

  // Insertar variables en plantillas.
  $$('[data-insertar]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const area = document.querySelector(btn.dataset.destino);
      const texto = btn.dataset.insertar;
      const ini = area.selectionStart ?? area.value.length;
      const fin = area.selectionEnd ?? area.value.length;
      area.value = area.value.slice(0, ini) + texto + area.value.slice(fin);
      area.focus();
      area.selectionStart = area.selectionEnd = ini + texto.length;
    });
  });

  // ---------- Exportar a Excel (SheetJS se carga en las páginas que lo usan) ----------
  const fechaArchivo = () => hoyISO(0);
  function sinLibreria() {
    if (window.XLSX) return false;
    window.alert('Todavía se está cargando el exportador. Probá de nuevo en un segundo.');
    return true;
  }

  $$('[data-exportar-tabla]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (sinLibreria()) return;
      const tabla = document.querySelector(btn.dataset.exportarTabla);
      const libro = XLSX.utils.table_to_book(tabla, { sheet: 'Datos', raw: true });
      XLSX.writeFile(libro, `${btn.dataset.nombre || 'listado'}-${fechaArchivo()}.xlsx`);
    });
  });

  $$('[data-exportar-url]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (sinLibreria()) return;
      const texto = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Exportando…';
      try {
        const resp = await fetch(btn.dataset.exportarUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!resp.ok) throw new Error();
        const { filas } = await resp.json();
        const hoja = XLSX.utils.json_to_sheet(filas);
        const libro = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(libro, hoja, 'Empresas');
        XLSX.writeFile(libro, `${btn.dataset.nombre || 'listado'}-${fechaArchivo()}.xlsx`);
      } catch (e) {
        window.alert('No se pudo exportar. Revisá la conexión.');
      } finally {
        btn.disabled = false;
        btn.textContent = texto;
      }
    });
  });

  // ---------- Ficha: registrar contacto ----------
  const form = document.getElementById('form-contacto');
  if (!form) return;

  const plantillas = JSON.parse(document.getElementById('plantillas-json').textContent || '[]');
  const selPlantilla = form.querySelector('#plantilla');
  const mensaje = form.querySelector('#mensaje');
  const asunto = form.querySelector('#asunto');
  const canalActual = () => (form.querySelector('input[name=canal]:checked') || {}).value;

  function aplicarCanal() {
    const canal = canalActual();
    $$('.solo-canal').forEach((el) => { el.hidden = el.dataset.canal !== canal; });
    // Solo se muestran las plantillas del canal elegido.
    Array.from(selPlantilla.options).forEach((op) => {
      if (!op.value) return;
      op.hidden = op.dataset.canal !== canal;
      op.disabled = op.hidden;
    });
    if (selPlantilla.selectedOptions[0]?.hidden) selPlantilla.value = '';
  }

  selPlantilla.addEventListener('change', () => {
    const p = plantillas.find((x) => String(x.id) === selPlantilla.value);
    if (!p) return;
    mensaje.value = p.cuerpo;
    if (asunto) asunto.value = p.asunto || '';
  });

  $$('input[name=canal]', form).forEach((r) => r.addEventListener('change', aplicarCanal));
  aplicarCanal();

  // Botones "WhatsApp" / "Email" de arriba: eligen el canal y bajan al formulario.
  $$('[data-canal-rapido]').forEach((a) => {
    a.addEventListener('click', () => {
      const radio = form.querySelector(`input[name=canal][value="${a.dataset.canalRapido}"]`);
      if (radio) { radio.checked = true; aplicarCanal(); }
    });
  });

  // Abrir WhatsApp/email con el mensaje y registrar el envío.
  $$('[data-abrir]', form).forEach((btn) => {
    btn.addEventListener('click', () => {
      const texto = mensaje.value.trim();
      let url;
      if (btn.dataset.abrir === 'whatsapp') {
        url = `https://wa.me/${form.dataset.wa}${texto ? `?text=${encodeURIComponent(texto)}` : ''}`;
        window.open(url, '_blank', 'noopener');
      } else {
        const params = [];
        if (asunto && asunto.value.trim()) params.push(`subject=${encodeURIComponent(asunto.value.trim())}`);
        if (texto) params.push(`body=${encodeURIComponent(texto)}`);
        url = `mailto:${form.dataset.email}${params.length ? `?${params.join('&')}` : ''}`;
        window.location.href = url;
      }
      // Pequeña espera para que el navegador abra la app antes de recargar la página.
      setTimeout(() => form.submit(), 400);
    });
  });
})();
