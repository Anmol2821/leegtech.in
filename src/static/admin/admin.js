// Lead tracker back button: return to the previous different screen in this tab.
// Keeps a small list of visited screens (this tab only); reloads of the same screen after saving are skipped.
(function () {
  var KEY = 'lt_nav', here = location.pathname + location.search, stack = [];
  try { stack = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) {}
  if (!Array.isArray(stack)) stack = [];
  var last = stack[stack.length - 1];
  if (last !== here) {
    if (stack[stack.length - 2] === here) stack.pop(); // came back to the previous screen
    else stack.push(here);
  }
  if (stack.length > 40) stack = stack.slice(-40);
  try { sessionStorage.setItem(KEY, JSON.stringify(stack)); } catch (e) {}
  var btn = document.getElementById('backbtn');
  if (!btn) return;
  btn.addEventListener('click', function (e) {
    var prev = stack[stack.length - 2];
    if (!prev) return; // no earlier screen: follow the link to the parent screen
    e.preventDefault();
    location.href = prev;
  });
})();
