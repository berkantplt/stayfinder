<style>
.auth-bg-image {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background-image: url('/images/auth-bg.jpg');
    background-size: cover;
    background-position: center;
    z-index: -2;
}
.auth-bg-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.28);
    z-index: -1;
}
</style>
<div class="auth-bg-image"></div>
<div class="auth-bg-overlay"></div>
