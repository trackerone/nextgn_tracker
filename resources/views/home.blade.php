@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/80 shadow-2xl shadow-slate-950/40">
            <div class="grid gap-6 p-6 lg:grid-cols-[1.5fr_1fr] lg:p-8">
                <div class="space-y-5">
                    <span class="inline-flex rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand">Private community tracker</span>
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight text-white md:text-4xl">Welcome back, {{ auth()->user()->name }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 md:text-base">
                            Browse verified releases, follow titles you care about, share clean uploads, and stay close to tracker discussions from one focused dashboard.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('torrents.index') }}" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/20">Browse torrents</a>
                        <a href="{{ route('torrents.upload') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand/60">Upload release</a>
                        <a href="#community" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand/60">Community activity</a>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ratio context</p>
                        <p class="mt-2 text-2xl font-semibold text-white">
                            @if ($userStats['ratio'] === null)
                                &infin;
                            @else
                                {{ number_format($userStats['ratio'], 2) }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-400">Class: {{ $userStats['class'] }} · Up {{ number_format($userStats['uploaded']) }} B · Down {{ number_format($userStats['downloaded']) }} B</p>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">What needs attention</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-300">
                            <a href="{{ route('my.follows') }}" class="flex items-center justify-between rounded-xl bg-slate-900/80 px-3 py-2 hover:text-white">
                                <span>New follow matches</span>
                                <strong class="text-emerald-300">{{ $followNewCount }}</strong>
                            </a>
                            <a href="{{ route('my.uploads') }}" class="flex items-center justify-between rounded-xl bg-slate-900/80 px-3 py-2 hover:text-white">
                                <span>Your pending uploads</span>
                                <strong class="text-amber-300">{{ $myOpenUploads }}</strong>
                            </a>
                            @if ($pendingModerationCount !== null)
                                <a href="{{ route('moderation.uploads') }}" class="flex items-center justify-between rounded-xl bg-slate-900/80 px-3 py-2 hover:text-white">
                                    <span>Moderation queue</span>
                                    <strong class="text-brand">{{ $pendingModerationCount }}</strong>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <section class="rounded-2xl border border-brand/30 bg-brand/10 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand">Livable Alpha orientation</p>
                    <h2 class="mt-2 text-xl font-semibold text-white">Start with the ordinary tracker path</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Browse a release, open its detail page, confirm the metadata and download status, then save the search or follow the release family if you want updates.</p>
                </div>
                <a href="{{ route('torrents.index') }}" class="inline-flex rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Start browsing</a>
            </div>
            <ol class="mt-5 grid gap-3 text-sm text-slate-300 md:grid-cols-5">
                <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3"><span class="font-semibold text-white">1. Browse</span><br>Use search or core filters.</li>
                <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3"><span class="font-semibold text-white">2. Compare</span><br>Pick a clean version.</li>
                <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3"><span class="font-semibold text-white">3. Check details</span><br>Review metadata and ratio status.</li>
                <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3"><span class="font-semibold text-white">4. Download</span><br>Use the torrent or magnet action.</li>
                <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3"><span class="font-semibold text-white">5. Follow</span><br>Save updates with metadata.</li>
            </ol>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5" aria-label="Tracker account tools">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tracker account tools</p>
                    <h2 class="mt-2 text-xl font-semibold text-white">Use your account area to keep the core loop moving</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">RSS feeds, watch presets, notifications, snatches, saved views, and upload status all connect back to browse and torrent details.</p>
                </div>
                <a href="{{ route('my.watch-center') }}" class="inline-flex rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand/60">Open watch center</a>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <a href="{{ route('account.rss.index') }}" class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 hover:border-brand/60"><span class="text-sm font-semibold text-white">RSS setup</span><span class="mt-1 block text-xs leading-5 text-slate-500">Generate feeds for current or saved filters.</span></a>
                <a href="{{ route('account.watch-presets.index') }}" class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 hover:border-brand/60"><span class="text-sm font-semibold text-white">Watch presets</span><span class="mt-1 block text-xs leading-5 text-slate-500">Follow reusable metadata intent.</span></a>
                <a href="{{ route('account.notifications.index') }}" class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 hover:border-brand/60"><span class="text-sm font-semibold text-white">Notifications</span><span class="mt-1 block text-xs leading-5 text-slate-500">Review matches and account activity.</span></a>
                <a href="{{ route('account.snatches') }}" class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 hover:border-brand/60"><span class="text-sm font-semibold text-white">Snatches</span><span class="mt-1 block text-xs leading-5 text-slate-500">Check your download history.</span></a>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <h2 class="text-base font-semibold text-white">Discover</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Use metadata-aware search, filters, follows, and the personalized feed to find releases without noise.</p>
                <a href="{{ route('my.discovery') }}" class="mt-4 inline-flex text-sm font-semibold text-brand hover:text-brand/80">Open Discovery →</a>
            </article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <h2 class="text-base font-semibold text-white">Contribute</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Upload with clear metadata, then track moderation feedback and publication status from your uploads page.</p>
                <a href="{{ route('my.uploads') }}" class="mt-4 inline-flex text-sm font-semibold text-brand hover:text-brand/80">View my uploads →</a>
            </article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <h2 class="text-base font-semibold text-white">Connect</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Forum topics and private messages keep requests, release notes, and community coordination close to the tracker.</p>
                <a href="#community" class="mt-4 inline-flex text-sm font-semibold text-brand hover:text-brand/80">See activity →</a>
            </article>
        </section>

        <section id="activity" class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Recent torrents</h2>
                        <p class="text-xs text-slate-400">Latest visible releases from the community.</p>
                    </div>
                    <a href="{{ route('torrents.index') }}" class="text-sm font-semibold text-brand hover:text-brand/80">Browse all</a>
                </div>
                <div class="divide-y divide-slate-800">
                    @forelse ($recentTorrents as $torrent)
                        <a href="{{ route('torrents.show', $torrent) }}" class="grid gap-2 px-5 py-4 hover:bg-slate-800/40 sm:grid-cols-[1fr_auto] sm:items-center">
                            <div>
                                <p class="font-semibold text-white">{{ $torrent->name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $torrent->category?->name ?? 'Uncategorized' }} · {{ $torrent->uploader?->name ?? 'Unknown uploader' }} · {{ optional($torrent->uploadedAtForDisplay())->diffForHumans() ?? 'Recently' }}</p>
                            </div>
                            <div class="flex gap-3 text-xs sm:justify-end">
                                <span class="text-emerald-300">{{ number_format($torrent->seeders) }} seed</span>
                                <span class="text-amber-300">{{ number_format($torrent->leechers) }} leech</span>
                                @if ((bool) ($torrent->is_freeleech ?? false))
                                    <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 font-semibold text-emerald-200">Freeleech</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-400">
                            No visible torrents yet. This is normal before the first approved uploads are published. Try Browse later or submit a release if you have upload access.
                        </div>
                    @endforelse
                </div>
            </div>

            <div id="community" class="space-y-6">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70">
                    <div class="border-b border-slate-800 px-5 py-4">
                        <h2 class="text-lg font-semibold text-white">Forum pulse</h2>
                        <p class="text-xs text-slate-400">Recent community topics and replies.</p>
                    </div>
                    <div class="divide-y divide-slate-800">
                        @forelse ($recentTopics as $topic)
                            <div class="px-5 py-4">
                                <p class="font-semibold text-white">{{ $topic->title }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $topic->posts_count }} posts · {{ $topic->author?->name ?? 'Unknown member' }} · {{ optional($topic->updated_at)->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-slate-400">No forum activity yet. Start with a release note, request, or introduction.</div>
                        @endforelse
                    </div>
                </div>

                <div id="messages" class="rounded-2xl border border-slate-800 bg-slate-900/70">
                    <div class="border-b border-slate-800 px-5 py-4">
                        <h2 class="text-lg font-semibold text-white">Private messages</h2>
                        <p class="text-xs text-slate-400">Recent conversations connected to your account.</p>
                    </div>
                    <div class="divide-y divide-slate-800">
                        @forelse ($recentConversations as $conversation)
                            @php
                                $currentUserId = (int) auth()->id();
                                $otherParticipant = (int) $conversation->user_a_id === $currentUserId ? $conversation->userB : $conversation->userA;
                            @endphp
                            <div class="px-5 py-4">
                                <p class="font-semibold text-white">{{ $otherParticipant?->name ?? 'Unknown member' }}</p>
                                <p class="mt-1 text-xs text-slate-400">Last message {{ optional($conversation->last_message_at)->diffForHumans() ?? 'not recorded' }}</p>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-slate-400">No private messages yet. Conversations will appear here once members contact each other.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
