</main>
<footer class="site-footer">
  <div class="container">
    <p><?= h(SITE_NAME) ?> —— 留住每一段值得被记住的声音</p>
    <p class="footer-note">部分内容仅家人可见，登录后可查看全部故事。</p>
  </div>
</footer>
<script>
document.getElementById('navToggle')?.addEventListener('click', function () {
  document.getElementById('mainNav').classList.toggle('open');
});
</script>
</body>
</html>
