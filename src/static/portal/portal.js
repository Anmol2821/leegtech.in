// Upload helper (client portal and tracker): forms with class "b64up" send the chosen file base64-encoded in a normal
// form field, because the host's firewall can block raw file uploads.
(function () {
  if (!window.FileReader) return;
  Array.prototype.forEach.call(document.querySelectorAll('form.b64up'), function (f) {
    f.addEventListener('submit', function (e) {
      var inp = f.querySelector('input[type=file]');
      var file = inp && inp.files && inp.files[0];
      if (!file) return;
      e.preventDefault();
      var max = parseInt(f.getAttribute('data-max') || '0', 10);
      if (max && file.size > max) { alert('That file is too large. The limit is ' + Math.round(max / 1048576 * 10) / 10 + ' MB.'); return; }
      var btn = f.querySelector('button');
      if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
      var r = new FileReader();
      r.onload = function () {
        var bytes = new Uint8Array(r.result), bin = '';
        for (var i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        f.querySelector('[name=file_b64]').value = btoa(bin);
        f.querySelector('[name=file_name]').value = file.name;
        inp.disabled = true;
        f.submit();
      };
      r.onerror = function () { if (btn) { btn.disabled = false; btn.textContent = 'Upload'; } alert('Could not read that file.'); };
      r.readAsArrayBuffer(file);
    });
  });
})();
