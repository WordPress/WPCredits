// What the rig draws over the page for one frame: the pointer, the mark of a press, the line of text at the
// bottom, and (when a press asked one) the browser's question with its own words. Run in the page with
// page.evaluate(overlayScript, state); it replaces whatever it drew for the frame before. Colors and type are
// the brand's (~/GitHub/DESIGN.md): ink #1e1e1e, primary #3858e9, Inter.
export function overlayScript(s) {
  const k = s.scale || 1; // overlay size, in CSS pixels per pilot pixel
  let root = document.getElementById('wpcpm-film-overlay');
  if (!root) {
    root = document.createElement('div');
    root.id = 'wpcpm-film-overlay';
    root.setAttribute('aria-hidden', 'true');
    root.style.cssText = 'position:fixed;inset:0;z-index:2147483647;pointer-events:none;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
    document.documentElement.appendChild(root);
  }
  const e = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  let html = '';
  if (s.dialog) {
    html += `<div style="position:absolute;inset:0;background:rgba(0,0,0,.12)"></div>
      <div style="position:absolute;top:14px;left:50%;transform:translateX(-50%);width:440px;background:#fff;border-radius:10px;box-shadow:0 4px 18px rgba(0,0,0,.28);padding:18px 20px 16px;color:#1e1e1e;">
        <div style="font-size:15px;font-weight:600;margin-bottom:8px">${e(s.dialog.title)}</div>
        <div style="font-size:14px;line-height:1.45;max-height:190px;overflow:hidden">${e(s.dialog.message)}</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <span style="display:inline-block;padding:7px 16px;border-radius:16px;border:1px solid #c7c7c7;color:#3858e9;font-size:14px">Cancel</span>
          <span class="dlg-ok" style="display:inline-block;padding:7px 22px;border-radius:16px;background:${s.dialog.pressed ? '#213fd4' : '#3858e9'};color:#fff;font-size:14px">OK</span>
        </div>
      </div>`;
  }
  if (s.caption) {
    html += `<div class="band" style="position:absolute;left:0;right:0;bottom:0;min-height:${s.band}px;display:flex;align-items:center;justify-content:center;background:#1e1e1e;color:#fff;padding:${10 * k}px ${28 * k}px;box-sizing:border-box;">
      <div style="font-size:${19 * k}px;line-height:1.35;font-weight:500;text-align:center;max-width:${900 * k}px">${e(s.caption)}</div></div>`;
  }
  if (s.keycap) {
    html += `<div class="keycap" style="position:absolute;right:${24 * k}px;bottom:${s.band + 14 * k}px;min-width:${46 * k}px;height:${46 * k}px;padding:0 ${12 * k}px;transform-origin:bottom right;box-sizing:border-box;display:flex;align-items:center;justify-content:center;border-radius:8px;background:${s.keyDown ? '#eff2ff' : '#fff'};border:2px solid ${s.keyDown ? '#3858e9' : '#8d8d8d'};box-shadow:0 ${s.keyDown ? 1 : 3}px 0 #8d8d8d;font-size:${22 * k}px;font-weight:600;color:#1e1e1e;transform:translateY(${s.keyDown ? 2 : 0}px)">${e(s.keycap)}</div>`;
  }
  if (s.mark) {
    html += `<div style="position:absolute;left:${s.x - 17 * k}px;top:${s.y - 17 * k}px;width:${34 * k}px;height:${34 * k}px;border-radius:50%;background:rgba(56,88,233,.28);border:${2 * k}px solid #3858e9;box-sizing:border-box"></div>`;
  }
  html += `<svg width="${26 * k}" height="${34 * k}" viewBox="0 0 26 34" style="position:absolute;left:${s.x - 2 * k}px;top:${s.y - 2 * k}px;filter:drop-shadow(0 1px 1.5px rgba(0,0,0,.45))">
      <path d="M2 2 L2 26 L8.2 20.2 L12.6 30.6 L17 28.7 L12.7 18.6 L21 18.6 Z" fill="#fff" stroke="#1e1e1e" stroke-width="1.8" stroke-linejoin="round"/></svg>`;
  root.innerHTML = html;
  // The key cap sits above the band as it is drawn, one line or two.
  const band = root.querySelector('.band'), cap = root.querySelector('.keycap');
  if (band && cap) cap.style.bottom = (band.offsetHeight + 14 * k) + 'px';
}
