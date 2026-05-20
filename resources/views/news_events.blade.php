{{-- Blade view for the news_events page in the RSRS application. --}}

@extends('layouts.app')

@section('title', 'News & Events - Road Safety Reporting System')

@section('content')
    @php
        use Illuminate\Support\Str;

        $recentIds = [];
        if (isset($announcements) && $announcements->isNotEmpty()) {
            $recentIds = $announcements
                ->sortByDesc('created_at')
                ->filter(function ($a) {
                    return isset($a->created_at) && $a->created_at->gt(\Carbon\Carbon::now()->subDays(5));
                })
                ->take(2)
                ->pluck('id')
                ->toArray();
        }

        $latestAnnouncementId = isset($announcements) && $announcements->isNotEmpty()
            ? $announcements->sortByDesc('created_at')->first()->id
            : null;

    @endphp

    <style>
        .news-page,
        .news-page p,
        .news-page li,
        .news-page span {
            color: #1d2a36;
            font-family: var(--font-body);
        }

        .news-page .home-section {
            font-family: var(--font-body);
            color: #1d2a36;
        }

        .news-page #latest-news {
            background: linear-gradient(180deg, #f9fdff 0%, #eef8ff 100%);
        }

        .news-page #latest-news::before {
            width: 360px;
            height: 360px;
            left: -180px;
            top: -120px;
            background: radial-gradient(circle, rgba(94, 196, 238, 0.2) 0%, rgba(94, 196, 238, 0.06) 60%, transparent 72%);
        }

        .news-page #latest-news::after {
            width: 280px;
            height: 280px;
            right: -140px;
            bottom: -120px;
            background: radial-gradient(circle, rgba(16, 152, 212, 0.16) 0%, rgba(16, 152, 212, 0.05) 62%, transparent 74%);
        }

        .news-page .section-intro,
        .news-page .news-card p {
            font-family: var(--font-body);
            font-weight: 400;
            line-height: 1.7;
            letter-spacing: 0.005em;
            color: var(--color-text-muted);
        }

        .news-page .section-title,
        .news-page .news-card h5,
        .news-page .news-list-title {
            font-family: var(--bs-body-font-family);
            font-weight: 500;
            letter-spacing: 0.01em;
            color: #1d2a36;
        }

        .news-page .section-title {
            font-size: clamp(1.55rem, 3vw, 1.95rem);
            margin-bottom: 0.9rem;
        }

        .news-page .section-title::after {
            width: 56px;
            height: 2px;
            margin-top: 0.8rem;
            opacity: 0.45;
        }

        .news-page .section-eyebrow {
            margin-bottom: 1rem;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.2rem;
        }

        .news-card {
            height: 100%;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 14px 34px rgba(13, 111, 155, 0.12);
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(16, 152, 212, 0.14);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .news-card:hover {
            transform: translateY(-4px);
            border-color: rgba(16, 152, 212, 0.24);
            box-shadow: 0 18px 38px rgba(13, 111, 155, 0.16);
        }

        .news-card-media {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(94, 196, 238, 0.22), transparent 28%),
                linear-gradient(135deg, rgba(243, 251, 255, 0.96), rgba(228, 245, 255, 0.96));
        }

        .news-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .news-card-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
            font-size: 2rem;
        }

        .badge-new {
            position: absolute;
            top: 14px;
            right: 14px;
            background: #ff3b30;
            color: #fff;
            padding: 6px 9px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.74rem;
            letter-spacing: 0.04em;
            box-shadow: 0 6px 20px rgba(255, 59, 48, 0.12);
            animation: newBlink 1s steps(2, start) infinite;
            z-index: 2;
        }

        @keyframes newBlink {
            50% {
                opacity: 0.15;
                transform: translateY(-1px);
            }
        }

        .news-card-body {
            min-height: 230px;
            display: flex;
            flex-direction: column;
            padding: 1.15rem;
        }

        .news-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.88rem;
            color: var(--color-text-muted);
            margin-bottom: 0.7rem;
        }

        .news-card h5 {
            font-size: 1.04rem;
            margin-bottom: 0.55rem;
            line-height: 1.45;
        }

        .news-card p {
            margin-bottom: 0;
            flex-grow: 1;
        }

        .news-card-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
        }

        .news-read-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-size: 0.84rem;
            padding: 0.52rem 0.85rem;
            border-radius: 999px;
            font-family: var(--bs-body-font-family);
        }

        .news-empty-state {
            padding: 2rem 1.4rem;
            border-radius: 22px;
            text-align: center;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(16, 152, 212, 0.14);
            box-shadow: 0 14px 34px rgba(13, 111, 155, 0.12);
            backdrop-filter: blur(10px);
        }

        .news-empty-state i {
            font-size: 2rem;
            color: var(--color-primary);
            margin-bottom: 0.75rem;
        }

        .news-modal .modal-content {
            border: 1px solid rgba(16, 152, 212, 0.14);
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(13, 111, 155, 0.14);
            overflow: hidden;
        }

        .news-modal .modal-header,
        .news-modal .modal-footer {
            background: rgba(243, 251, 255, 0.96);
            border-color: rgba(16, 152, 212, 0.12);
        }

        .news-modal .modal-title {
            font-family: var(--bs-body-font-family);
            font-weight: 500;
            color: #1d2a36;
        }

        @media (max-width: 1199.98px) {
            .news-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991px) {
            .news-card-media {
                height: 190px;
            }
        }

        @media (max-width: 767px) {
            .news-grid {
                grid-template-columns: 1fr;
            }

            .news-card-media {
                height: 170px;
            }
        }

        @media (max-width: 575.98px) {
            .news-page .section-intro,
            .news-page .news-card p {
                font-size: 0.92rem;
                line-height: 1.65;
            }

            .news-page .section-title {
                font-size: 1.38rem;
                font-weight: 500;
                letter-spacing: 0.008em;
                margin-bottom: 0.75rem;
            }

            .news-page .section-title::after {
                width: 44px;
                margin-top: 0.65rem;
            }
        }
    </style>

    <div class="news-page">
        <section class="home-section" id="latest-news">
            <div class="container">
                <div class="section-shell">
                    <div class="text-center mb-4">
                        <span class="section-eyebrow">
                            <i class="bi bi-broadcast-pin"></i> School Updates
                        </span>
                        <h2 class="section-title mb-2">Latest News & Events</h2>
                        <p class="section-intro mb-0">
                            Browse recent school stories, event highlights and public notices in one place.
                        </p>
                    </div>

                    @if (isset($announcements) && $announcements->isEmpty())
                        <div class="news-empty-state">
                            <i class="bi bi-newspaper"></i>
                            <h5 class="mb-2">No public updates yet</h5>
                            <p class="mb-0">New announcements and event highlights will appear here once they are published.</p>
                        </div>
                    @else
                        <div class="news-grid">
                            @foreach ($announcements ?? collect() as $ann)
                                @php
                                    $ext = strtolower(pathinfo($ann->attachment ?? '', PATHINFO_EXTENSION));
                                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                                    $isImage = !empty($ann->attachment) && in_array($ext, $imageExts);
                                    $isNew = $ann->id === $latestAnnouncementId || in_array($ann->id, $recentIds);
                                @endphp
                                <article class="news-card">
                                    <div class="news-card-media">
                                        @if ($isNew)
                                            <span class="badge-new">NEW</span>
                                        @endif
                                        @if ($isImage)
                                            <img src="{{ route('news-events.attachment', $ann) }}" alt="{{ $ann->title }}" class="news-image" />
                                        @else
                                            <div class="news-card-placeholder">
                                                <i class="bi bi-megaphone-fill"></i>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="news-card-body">
                                        <div class="news-meta">
                                            <i class="bi bi-calendar-event"></i>
                                            <span>{{ $ann->created_at->format('d M Y') }}</span>
                                        </div>

                                        <h5>{{ Str::limit($ann->title, 80) }}</h5>
                                        <p>{{ Str::limit(strip_tags($ann->body), 140) }}</p>

                                        <div class="news-card-actions">
                                            <button type="button" class="btn btn-outline-primary news-read-btn open-announcement" data-id="{{ $ann->id }}">
                                                <i class="bi {{ $ext === 'pdf' ? 'bi-file-earmark-arrow-down' : 'bi-arrow-up-right-circle' }}"></i>
                                                <span>{{ $ext === 'pdf' ? 'Read / Download' : 'Read More' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade news-modal" id="announcementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementModalLabel">Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="announcementMedia" class="mb-3 text-center"></div>
                    <div id="announcementBody" class="fs-6 text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.announcementsData = {
            @if (isset($announcements) && $announcements->isNotEmpty())
                @foreach ($announcements as $a)
                    "{{ $a->id }}": {
                        id: {{ $a->id }},
                        title: {!! json_encode($a->title) !!},
                        body: {!! json_encode(nl2br(e($a->body))) !!},
                        attachment: {!! json_encode($a->attachment) !!},
                        attachment_url: {!! json_encode($a->attachment ? route('news-events.attachment', $a) : null) !!},
                        download_url: {!! json_encode($a->attachment ? route('news-events.attachment', ['announcement' => $a->id, 'download' => 1]) : null) !!},
                        ext: {!! json_encode(strtolower(pathinfo($a->attachment ?? '', PATHINFO_EXTENSION))) !!}
                    }@if (!$loop->last),@endif
                @endforeach
            @endif
        };

        (function() {
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.open-announcement');
                if (!btn) return;
                var id = btn.getAttribute('data-id');
                var data = window.announcementsData[id];
                if (!data) return;

                var modalTitle = document.getElementById('announcementModalLabel');
                var media = document.getElementById('announcementMedia');
                var body = document.getElementById('announcementBody');
                modalTitle.innerText = data.title || 'Announcement';
                media.innerHTML = '';
                body.innerHTML = data.body || '';

                var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                if (data.attachment_url) {
                    var ext = (data.ext || '').toLowerCase();
                    if (imageExts.indexOf(ext) !== -1) {
                        var img = document.createElement('img');
                        img.src = data.attachment_url;
                        img.className = 'img-fluid rounded';
                        img.style.maxHeight = '480px';
                        media.appendChild(img);
                    } else if (ext === 'pdf') {
                        var iframe = document.createElement('iframe');
                        iframe.src = data.attachment_url;
                        iframe.style.width = '100%';
                        iframe.style.height = '480px';
                        iframe.frameBorder = 0;
                        media.appendChild(iframe);
                    } else {
                        var link = document.createElement('a');
                        link.href = data.download_url || data.attachment_url;
                        link.target = '_blank';
                        link.className = 'btn btn-outline-primary';
                        link.innerText = 'Open attachment';
                        media.appendChild(link);
                    }
                }

                var modalEl = document.getElementById('announcementModal');
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            });

        })();
    </script>
@endsection
