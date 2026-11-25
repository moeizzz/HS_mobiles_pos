      </div>
      <!-- /.content -->

      <!-- Footer -->
      <footer class="sticky-footer">
        <div class="container">
          <div class="copyright text-center">
            &copy; <?= date('Y') ?> <?= shop('name') ?: 'HS Mobiles' ?>. All rights reserved.
          </div>
        </div>
      </footer>
    </div>
    <!-- End of Content Wrapper -->
  </div>
  <!-- End of Wrapper -->

  <!-- Scroll to Top -->
  <a class="scroll-to-top" href="#page-top" id="scrollToTop">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Logout Modal -->
  <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Ready to Leave?</h5>
          <button class="close" type="button" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">Select "Logout" to end your session.</div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
          <a class="btn btn-primary" href="<?= $base_url ?? '' ?>login.php?logout=1">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });
    });
  </script>
  <script>
    // CSRF: attach the session token to every POST form and to every delete (?del...=) link.
    (function () {
      var m = document.querySelector('meta[name="csrf-token"]');
      if (!m) return;
      var token = m.getAttribute('content');
      function addToken(f) {
        if (!f || !f.tagName || f.tagName !== 'FORM') return;
        if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        if (f.querySelector('input[name="csrf"]')) return;
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'csrf'; i.value = token;
        f.appendChild(i);
      }
      function fixLink(a) {
        var h = a.getAttribute('href');
        if (!h || h.indexOf('csrf=') > -1) return;
        if (/[?&]del[a-z_]*=/i.test(h)) a.setAttribute('href', h + (h.indexOf('?') > -1 ? '&' : '?') + 'csrf=' + encodeURIComponent(token));
      }
      document.querySelectorAll('form').forEach(addToken);
      document.querySelectorAll('a[href]').forEach(fixLink);
      document.addEventListener('submit', function (e) { addToken(e.target); }, true);
      document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (a) fixLink(a);
      }, true);
    })();
  </script>
  <script src="<?= $base_url ?? '' ?>assets/js/main.js"></script>
  <script src="<?= $base_url ?? '' ?>assets/js/pos-ui.js"></script>
  <div id="toastHost"></div>
</body>
</html>
