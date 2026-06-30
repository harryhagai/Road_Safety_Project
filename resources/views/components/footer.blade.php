{{-- Reusable Blade component used across multiple RSRS pages. --}}

<footer class="footer-wrapper">
    <div class="footer-container">
        <div class="footer-line">
            <div class="footer-copyright">
                &copy; {{ date('Y') }} Road Safety Reporting System.
            </div>
            <span class="footer-dot" aria-hidden="true">•</span>
            <nav class="footer-nav-slim" aria-label="Footer navigation">
                <a href="{{ route('developer') }}" class="footer-dev-link">
                    <i class="bi bi-code-slash" aria-hidden="true"></i>
                    Developers
                </a>
            </nav>
        </div>
    </div>
</footer>
