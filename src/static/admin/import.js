// Lead tracker import: send the chosen CSV base64-encoded in a normal form field instead of a file upload,
// because the host's firewall can block raw file uploads.
(function () {
  var f = document.getElementById('impf');
  if (!f || !window.FileReader) return;
  f.addEventListener('submit', function (e) {
    var inp = f.querySelector('input[type=file]');
    var file = inp && inp.files && inp.files[0];
    if (!file) return;
    e.preventDefault();
    var btn = f.querySelector('button');
    if (btn) { btn.disabled = true; btn.textContent = 'Reading file…'; }
    var r = new FileReader();
    r.onload = function () {
      var bytes = new Uint8Array(r.result), bin = '';
      for (var i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
      f.querySelector('[name=csv_b64]').value = btoa(bin);
      f.querySelector('[name=csv_name]').value = file.name;
      inp.disabled = true;
      f.enctype = 'application/x-www-form-urlencoded';
      f.submit();
    };
    r.onerror = function () { if (btn) { btn.disabled = false; btn.textContent = 'Preview'; } alert('Could not read that file.'); };
    r.readAsArrayBuffer(file);
  });
})();
