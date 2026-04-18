import { Head, Link, usePage } from '@inertiajs/react';
import {
    FileText,
    ImageIcon,
    Info,
    Link2,
    ScanLine,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useState,
} from 'react';
import type { ComponentType, DragEvent } from 'react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

type InputMode = 'text' | 'url' | 'image';

type AnalysisResult = {
    verdict: 'REAL' | 'FAKE' | 'UNCERTAIN';
    verdictHint: string;
    confidence: number;
    confidenceHint: string;
    realPercent: number;
    fakePercent: number;
    explanation: string;
    topics: string[];
};

type ApiResult = {
    verdict: string;
    verdict_hint: string;
    confidence: number;
    confidence_hint: string;
    real_percent: number;
    fake_percent: number;
    explanation: string;
    topics: string[];
};

type LandingSegment =
    | { type: 'text'; value: string }
    | { type: 'code'; value: string }
    | { type: 'link'; value: string; href: string };

type LandingCopy = {
    meta: { document_title: string };
    header: { brand: string; tagline: string };
    nav: { dashboard: string; login: string; register: string };
    provider: {
        gemini: { short: string; long: string };
        groq: { short: string; long: string };
    };
    hero: {
        badge: string;
        headline: string;
        intro_before_provider: string;
        intro_after_provider: string;
        intro_driver_env: string;
        intro_after_code: string;
    };
    api_key_warning: {
        title: string;
        gemini: LandingSegment[];
        groq: LandingSegment[];
        suffix: LandingSegment[];
    };
    input_card: {
        title: string;
        description: string;
        tablist_aria: string;
        modes: { text: string; url: string; image: string };
        active_source_prefix: string;
        active_source_suffix: string;
        active_source: {
            image_named: string;
            url: string;
            text: string;
            url_empty: string;
            image_none: string;
            text_empty: string;
        };
        text_label: string;
        text_placeholder: string;
        url_label: string;
        url_placeholder: string;
        url_help: string;
        image_help: string;
        image_drop: string;
        image_choose: string;
        image_remove: string;
        analyze: string;
        analyzing: string;
        reset: string;
    };
    summary_card: {
        title: string;
        description: string;
        empty: string;
        empty_strong: string;
        empty_after: string;
        calling_ai: string;
        verdict: string;
        confidence: string;
        real: string;
        fake: string;
        explanation: string;
        related_topics: string;
    };
    footer: { text: string };
    toasts: {
        analysis_complete: string;
        api_key_missing_gemini: string;
        api_key_missing_groq: string;
        generic_error: string;
    };
};

function readXsrfToken(): string {
    const m = document.cookie.match(
        /(?:^|; )XSRF-TOKEN=([^;]*)/,
    );

    return m ? decodeURIComponent(m[1]) : '';
}

function verdictAccentClass(verdict: string): string {
    switch (verdict) {
        case 'REAL':
            return 'border-emerald-200/80 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-50';
        case 'FAKE':
            return 'border-rose-200/80 bg-rose-50/90 text-rose-950 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-50';
        default:
            return 'border-amber-200/80 bg-amber-50/90 text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-50';
    }
}

function LandingSegments({ segments }: { segments: LandingSegment[] }) {
    const codeClass =
        'rounded bg-amber-100/80 px-1 font-mono text-xs dark:bg-amber-900/50';

    return (
        <>
            {segments.map((s, i) => {
                if (s.type === 'code') {
                    return (
                        <code key={i} className={codeClass}>
                            {s.value}
                        </code>
                    );
                }

                if (s.type === 'link') {
                    return (
                        <a
                            key={i}
                            className="underline"
                            href={s.href}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {s.value}
                        </a>
                    );
                }

                return <span key={i}>{s.value}</span>;
            })}
        </>
    );
}

function mapApiResult(r: ApiResult): AnalysisResult {
    const v = r.verdict.toUpperCase();
    const verdict: AnalysisResult['verdict'] =
        v === 'REAL' || v === 'FAKE' || v === 'UNCERTAIN' ? v : 'UNCERTAIN';

    return {
        verdict,
        verdictHint: r.verdict_hint,
        confidence: r.confidence,
        confidenceHint: r.confidence_hint,
        realPercent: r.real_percent,
        fakePercent: r.fake_percent,
        explanation: r.explanation,
        topics: Array.isArray(r.topics) ? r.topics : [],
    };
}

export default function Welcome({
    analyzerConfigured = false,
    analyzerDriver = 'gemini',
    landing,
}: {
    analyzerConfigured?: boolean;
    analyzerDriver?: string;
    landing: LandingCopy;
}) {
    const { auth } = usePage().props;
    const copy = landing;
    const providerKey = analyzerDriver === 'groq' ? 'groq' : 'gemini';
    const providerShort = copy.provider[providerKey].short;
    const providerLong = copy.provider[providerKey].long;
    const fileInputId = useId();

    const [mode, setMode] = useState<InputMode>('text');
    const [text, setText] = useState('');
    const [url, setUrl] = useState('');
    const [imageFile, setImageFile] = useState<File | null>(null);

    const [analyzing, setAnalyzing] = useState(false);
    const [result, setResult] = useState<AnalysisResult | null>(null);

    const imagePreview = useMemo(
        () => (imageFile ? URL.createObjectURL(imageFile) : null),
        [imageFile],
    );

    useEffect(() => {
        return () => {
            if (imagePreview) {
                URL.revokeObjectURL(imagePreview);
            }
        };
    }, [imagePreview]);

    const activeSourceLabel = (() => {
        const a = copy.input_card.active_source;

        if (mode === 'image' && imageFile) {
            return a.image_named.replace(':name', imageFile.name);
        }

        if (mode === 'url' && url.trim()) {
            return a.url;
        }

        if (mode === 'text' && text.trim()) {
            return a.text;
        }

        if (mode === 'url') {
            return a.url_empty;
        }

        if (mode === 'image') {
            return a.image_none;
        }

        return a.text_empty;
    })();

    const analyze = useCallback(async () => {
        if (!analyzerConfigured) {
            toast.error(
                analyzerDriver === 'groq'
                    ? copy.toasts.api_key_missing_groq
                    : copy.toasts.api_key_missing_gemini,
            );

            return;
        }

        setAnalyzing(true);
        setResult(null);

        try {
            const fd = new FormData();
            fd.append('mode', mode);

            if (mode === 'text') {
                fd.append('text', text);
            }

            if (mode === 'url') {
                fd.append('url', url);
            }

            if (mode === 'image' && imageFile) {
                fd.append('image', imageFile);
            }

            const res = await fetch('/analyze', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: fd,
            });

            const data = (await res.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
                result?: ApiResult;
            };

            if (!res.ok) {
                if (res.status === 422 && data.errors) {
                    const first = Object.values(data.errors).flat()[0];

                    throw new Error(
                        first ?? data.message ?? 'Validation failed',
                    );
                }

                throw new Error(data.message ?? 'Analysis failed');
            }

            if (!data.result) {
                throw new Error('Invalid response from server');
            }

            setResult(mapApiResult(data.result));
            toast.success(copy.toasts.analysis_complete);
        } catch (e) {
            const msg =
                e instanceof Error
                    ? e.message
                    : copy.toasts.generic_error;
            toast.error(msg);
        } finally {
            setAnalyzing(false);
        }
    }, [
        analyzerConfigured,
        analyzerDriver,
        copy,
        mode,
        text,
        url,
        imageFile,
    ]);

    const resetAll = useCallback(() => {
        setText('');
        setUrl('');
        setImageFile(null);
        setResult(null);
        setAnalyzing(false);
    }, []);

    const onImageDrop = useCallback((e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        const f = e.dataTransfer.files?.[0];

        if (f?.type.startsWith('image/')) {
            setMode('image');
            setImageFile(f);
        }
    }, []);

    const onImageDragOver = useCallback((e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
    }, []);

    const modeTabs: {
        id: InputMode;
        label: string;
        Icon: ComponentType<{ className?: string }>;
    }[] = [
        { id: 'text', label: copy.input_card.modes.text, Icon: FileText },
        { id: 'url', label: copy.input_card.modes.url, Icon: Link2 },
        { id: 'image', label: copy.input_card.modes.image, Icon: ImageIcon },
    ];

    return (
        <>
            <Head title={copy.meta.document_title}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>
            <div className="relative min-h-screen overflow-x-hidden bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50">
                <div
                    className="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(120,119,198,0.12),transparent)] dark:bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(120,119,198,0.08),transparent)]"
                    aria-hidden
                />
                <div
                    className="pointer-events-none fixed inset-0 -z-10 bg-[linear-gradient(to_bottom,transparent,rgba(244,244,245,0.8))] dark:bg-[linear-gradient(to_bottom,transparent,rgba(9,9,11,0.85))]"
                    aria-hidden
                />
                <header className="sticky top-0 z-40 border-b border-zinc-200/70 bg-white/85 backdrop-blur-md dark:border-zinc-800/80 dark:bg-zinc-950/85">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900">
                                <ShieldCheck className="size-5" strokeWidth={2} />
                            </div>
                            <div className="flex flex-col gap-0.5">
                                <span className="text-base font-semibold tracking-tight">
                                    {copy.header.brand}
                                </span>
                                <span className="text-xs text-zinc-500 dark:text-zinc-400">
                                    {copy.header.tagline}
                                </span>
                            </div>
                        </div>
                        <nav className="flex shrink-0 items-center gap-2 sm:gap-3">
                            {auth.user ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={dashboard()}>
                                        {copy.nav.dashboard}
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="sm" asChild>
                                    <Link href={login()}>
                                        {copy.nav.login}
                                    </Link>
                                </Button>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
                    <div className="mb-10 max-w-2xl space-y-4">
                        <div className="inline-flex items-center gap-2 rounded-full border border-zinc-200/80 bg-white/90 px-3 py-1 text-xs font-medium text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/90 dark:text-zinc-300">
                            <Sparkles className="size-3.5 text-amber-500 dark:text-amber-400" />
                            {copy.hero.badge}
                            <span className="rounded-md bg-zinc-100 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                {providerShort}
                            </span>
                        </div>
                        <h1 className="text-balance text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-zinc-50">
                            {copy.hero.headline}
                        </h1>
                        <p className="text-pretty text-sm leading-relaxed text-zinc-600 sm:text-base dark:text-zinc-400">
                            {copy.hero.intro_before_provider}
                            {' '}
                            <span className="font-medium text-zinc-800 dark:text-zinc-200">
                                {providerLong}
                            </span>
                            {copy.hero.intro_after_provider ? (
                                <>
                                    {' '}
                                    {copy.hero.intro_after_provider}
                                </>
                            ) : null}
                            {copy.hero.intro_driver_env ? (
                                <>
                                    {' '}
                                    <code className="rounded-md bg-zinc-200/80 px-1.5 py-0.5 font-mono text-xs dark:bg-zinc-800">
                                        {copy.hero.intro_driver_env}
                                    </code>
                                </>
                            ) : null}
                            {copy.hero.intro_after_code ? (
                                <>
                                    {' '}
                                    {copy.hero.intro_after_code}
                                </>
                            ) : null}
                        </p>
                    </div>

                    {!analyzerConfigured && (
                        <div
                            className="mb-8 flex gap-3 rounded-2xl border border-amber-200/90 bg-amber-50/95 px-4 py-4 text-sm text-amber-950 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/35 dark:text-amber-100"
                            role="status"
                        >
                            <div className="mt-0.5 shrink-0">
                                <Info className="size-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p className="font-semibold">
                                    {copy.api_key_warning.title}
                                </p>
                                <p className="mt-1 text-amber-900/90 dark:text-amber-200/90">
                                    <LandingSegments
                                        segments={
                                            analyzerDriver === 'groq'
                                                ? copy.api_key_warning.groq
                                                : copy.api_key_warning.gemini
                                        }
                                    />{' '}
                                    <LandingSegments
                                        segments={
                                            copy.api_key_warning.suffix
                                        }
                                    />
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-8 lg:grid-cols-2 lg:items-start lg:gap-10">
                        {/* Input */}
                        <Card className="border-zinc-200/90 shadow-md shadow-zinc-200/40 dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-none dark:ring-1 dark:ring-zinc-800/80">
                            <CardHeader className="space-y-1 border-b border-zinc-100 pb-4 dark:border-zinc-800/80">
                                <div className="flex items-center gap-2">
                                    <ScanLine className="size-5 text-zinc-500 dark:text-zinc-400" />
                                    <CardTitle className="text-lg">
                                        {copy.input_card.title}
                                    </CardTitle>
                                </div>
                                <CardDescription>
                                    {copy.input_card.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div
                                    className="flex rounded-xl border border-zinc-200/90 bg-zinc-100/90 p-1 dark:border-zinc-700 dark:bg-zinc-950/80"
                                    role="tablist"
                                    aria-label={copy.input_card.tablist_aria}
                                >
                                    {modeTabs.map((tab) => {
                                        const TabIcon = tab.Icon;

                                        return (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={mode === tab.id}
                                            className={cn(
                                                'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                                mode === tab.id
                                                    ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200/80 dark:bg-zinc-900 dark:text-zinc-50 dark:ring-zinc-700'
                                                    : 'text-zinc-600 hover:bg-white/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-200',
                                            )}
                                            onClick={() => setMode(tab.id)}
                                        >
                                            <TabIcon className="size-4 shrink-0 opacity-80" />
                                            {tab.label}
                                        </button>
                                        );
                                    })}
                                </div>

                                <p className="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                    {copy.input_card.active_source_prefix}
                                    {activeSourceLabel}
                                    {copy.input_card.active_source_suffix}
                                </p>

                                {mode === 'text' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="news-text">
                                            {copy.input_card.text_label}
                                        </Label>
                                        <textarea
                                            id="news-text"
                                            value={text}
                                            onChange={(e) =>
                                                setText(e.target.value)
                                            }
                                            rows={8}
                                            placeholder={
                                                copy.input_card.text_placeholder
                                            }
                                            className={cn(
                                                'border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                            )}
                                        />
                                    </div>
                                )}

                                {mode === 'url' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="news-url">
                                            {copy.input_card.url_label}
                                        </Label>
                                        <Input
                                            id="news-url"
                                            type="url"
                                            inputMode="url"
                                            placeholder={
                                                copy.input_card.url_placeholder
                                            }
                                            value={url}
                                            onChange={(e) =>
                                                setUrl(e.target.value)
                                            }
                                        />
                                        <p className="text-xs text-zinc-500 dark:text-zinc-400">
                                            {copy.input_card.url_help}
                                        </p>
                                    </div>
                                )}

                                {mode === 'image' && (
                                    <div className="space-y-3">
                                        <input
                                            id={fileInputId}
                                            type="file"
                                            accept="image/*"
                                            className="sr-only"
                                            onChange={(e) => {
                                                const f =
                                                    e.target.files?.[0] ?? null;
                                                setImageFile(f);
                                            }}
                                        />
                                        <div
                                            className={cn(
                                                'rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50/50 p-4 transition-colors dark:border-zinc-600 dark:bg-zinc-900/40',
                                                imagePreview && 'border-solid border-zinc-200 dark:border-zinc-700',
                                            )}
                                            onDragOver={onImageDragOver}
                                            onDrop={onImageDrop}
                                        >
                                            {imagePreview ? (
                                                <div className="space-y-3">
                                                    <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-950">
                                                        <img
                                                            src={imagePreview}
                                                            alt=""
                                                            className="max-h-48 w-full object-contain"
                                                        />
                                                    </div>
                                                    <p className="text-xs text-zinc-600 dark:text-zinc-400">
                                                        {copy.input_card.image_help}
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="link"
                                                        className="h-auto p-0 text-sm"
                                                        onClick={() =>
                                                            setImageFile(null)
                                                        }
                                                    >
                                                        {
                                                            copy.input_card
                                                                .image_remove
                                                        }
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="flex flex-col items-center gap-3 py-8 text-center">
                                                    <div className="flex size-12 items-center justify-center rounded-2xl bg-zinc-200/80 dark:bg-zinc-800">
                                                        <ImageIcon className="size-6 text-zinc-500 dark:text-zinc-400" />
                                                    </div>
                                                    <p className="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {copy.input_card.image_drop}
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            document
                                                                .getElementById(
                                                                    fileInputId,
                                                                )
                                                                ?.click()
                                                        }
                                                    >
                                                        {
                                                            copy.input_card
                                                                .image_choose
                                                        }
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                <div className="flex flex-col gap-2 pt-2">
                                    <Button
                                        type="button"
                                        className="w-full rounded-xl font-semibold shadow-sm"
                                        size="lg"
                                        disabled={
                                            analyzing || !analyzerConfigured
                                        }
                                        onClick={() => void analyze()}
                                    >
                                        {analyzing ? (
                                            <>
                                                <Spinner />
                                                {copy.input_card.analyzing}
                                            </>
                                        ) : (
                                            copy.input_card.analyze
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full rounded-xl"
                                        size="lg"
                                        onClick={resetAll}
                                        disabled={analyzing}
                                    >
                                        {copy.input_card.reset}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Summary */}
                        <Card className="border-zinc-200/90 shadow-md shadow-zinc-200/40 dark:border-zinc-800 dark:bg-zinc-900/50 dark:shadow-none dark:ring-1 dark:ring-zinc-800/80">
                            <CardHeader className="space-y-1 border-b border-zinc-100 pb-4 dark:border-zinc-800/80">
                                <div className="flex items-center gap-2">
                                    <ScanLine className="size-5 text-zinc-500 dark:text-zinc-400" />
                                    <CardTitle className="text-lg">
                                        {copy.summary_card.title}
                                    </CardTitle>
                                </div>
                                <CardDescription>
                                    {copy.summary_card.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                {!result && !analyzing && (
                                    <div className="flex flex-col items-center rounded-xl border border-dashed border-zinc-200 bg-gradient-to-b from-zinc-50/80 to-transparent px-6 py-12 text-center dark:border-zinc-700 dark:from-zinc-900/40">
                                        <div className="mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                            <ScanLine className="size-7 text-zinc-400 dark:text-zinc-500" />
                                        </div>
                                        <p className="max-w-[260px] text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                                            {copy.summary_card.empty}
                                            <strong className="text-zinc-800 dark:text-zinc-200">
                                                {copy.summary_card.empty_strong}
                                            </strong>
                                            {copy.summary_card.empty_after}
                                        </p>
                                    </div>
                                )}

                                {analyzing && (
                                    <div className="flex flex-col items-center justify-center gap-3 py-14 text-sm text-zinc-500">
                                        <Spinner className="size-8" />
                                        <span className="font-medium">
                                            {copy.summary_card.calling_ai}
                                        </span>
                                    </div>
                                )}

                                {result && !analyzing && (
                                    <>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div
                                                className={cn(
                                                    'rounded-xl border-2 p-4 shadow-sm transition-colors',
                                                    verdictAccentClass(
                                                        result.verdict,
                                                    ),
                                                )}
                                            >
                                                <p className="text-[10px] font-semibold uppercase tracking-wider opacity-70">
                                                    {copy.summary_card.verdict}
                                                </p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums">
                                                    {result.verdict}
                                                </p>
                                                <p className="mt-1 text-xs opacity-90">
                                                    {result.verdictHint}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-950">
                                                <p className="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                                    {copy.summary_card.confidence}
                                                </p>
                                                <p className="mt-1 text-2xl font-bold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50">
                                                    {result.confidence}%
                                                </p>
                                                <p className="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                                                    {result.confidenceHint}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <div>
                                                <div className="mb-1.5 flex justify-between text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                                    <span>
                                                        {copy.summary_card.real}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {result.realPercent}%
                                                    </span>
                                                </div>
                                                <div className="h-2.5 overflow-hidden rounded-full bg-zinc-200/90 shadow-inner dark:bg-zinc-800">
                                                    <div
                                                        className="h-full rounded-full bg-gradient-to-r from-emerald-600 to-emerald-500 transition-all dark:from-emerald-500 dark:to-emerald-400"
                                                        style={{
                                                            width: `${result.realPercent}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <div className="mb-1.5 flex justify-between text-xs font-medium text-zinc-600 dark:text-zinc-400">
                                                    <span>
                                                        {copy.summary_card.fake}
                                                    </span>
                                                    <span className="tabular-nums">
                                                        {result.fakePercent}%
                                                    </span>
                                                </div>
                                                <div className="h-2.5 overflow-hidden rounded-full bg-zinc-200/90 shadow-inner dark:bg-zinc-800">
                                                    <div
                                                        className="h-full rounded-full bg-gradient-to-r from-rose-500 to-rose-400 transition-all dark:from-rose-500 dark:to-rose-400"
                                                        style={{
                                                            width: `${result.fakePercent}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-zinc-200 bg-zinc-50/90 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                                            <p className="text-[10px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                                {copy.summary_card.explanation}
                                            </p>
                                            <p className="mt-2 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                                                {result.explanation}
                                            </p>
                                        </div>

                                        <div>
                                            <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                                {copy.summary_card.related_topics}
                                            </p>
                                            <div className="flex flex-wrap gap-2">
                                                {result.topics.map((t) => (
                                                    <Badge
                                                        key={t}
                                                        variant="secondary"
                                                        className="rounded-lg border-zinc-200/80 bg-white font-normal text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                                    >
                                                        {t}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <footer className="mt-16 border-t border-zinc-200/80 py-10 text-center dark:border-zinc-800">
                        <p className="mx-auto max-w-md text-xs leading-relaxed text-zinc-500 dark:text-zinc-500">
                            {copy.footer.text}
                        </p>
                    </footer>
                </main>
            </div>
        </>
    );
}
