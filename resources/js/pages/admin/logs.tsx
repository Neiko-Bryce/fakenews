import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { destroy, index as adminLogsIndex } from '@/routes/admin/logs';

type LogUser = {
    name: string;
    email: string;
};

/** Mirrors API `NewsAnalyzer::analyze` normalized shape (snake_case from Laravel JSON). */
export type AnalysisResultPayload = {
    verdict: string;
    verdict_hint: string;
    confidence: number;
    confidence_hint: string;
    real_percent: number;
    fake_percent: number;
    explanation: string;
    topics: string[];
};

export type AnalysisLogRow = {
    id: number;
    created_at: string;
    mode: string;
    status: string;
    http_status: number | null;
    has_image: boolean;
    image_bytes: number | null;
    image_mime: string | null;
    image_client_name: string | null;
    text_length: number | null;
    has_url: boolean;
    ip_address: string | null;
    user: LogUser | null;
    error_message: string | null;
    analysis_result: AnalysisResultPayload | null;
};

type PaginatedLogs = {
    data: AnalysisLogRow[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PageProps = {
    logs: PaginatedLogs;
};

function formatBytes(n: number | null): string {
    if (n == null) {
        return '—';
    }

    if (n < 1024) {
        return `${n} B`;
    }

    if (n < 1024 * 1024) {
        return `${(n / 1024).toFixed(1)} KB`;
    }

    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function statusBadgeClass(status: string): string {
    if (status === 'success') {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400';
    }

    return 'bg-destructive/15 text-destructive';
}

function verdictBadgeClass(verdict: string): string {
    switch (verdict.toUpperCase()) {
        case 'REAL':
            return 'border-emerald-500/40 bg-emerald-500/15 text-emerald-800 dark:text-emerald-300';
        case 'FAKE':
            return 'border-rose-500/40 bg-rose-500/15 text-rose-800 dark:text-rose-300';
        default:
            return 'border-amber-500/40 bg-amber-500/15 text-amber-900 dark:text-amber-200';
    }
}

function normalizeVerdict(
    v: string,
): 'REAL' | 'FAKE' | 'UNCERTAIN' {
    const u = v.toUpperCase();

    return u === 'REAL' || u === 'FAKE' || u === 'UNCERTAIN' ? u : 'UNCERTAIN';
}

function RequestBadge({ row }: { row: AnalysisLogRow }) {
    return (
        <span
            className={`inline-flex rounded-md px-2 py-0.5 text-xs font-medium ${statusBadgeClass(row.status)}`}
        >
            {row.status}
            {row.http_status != null && ` · ${row.http_status}`}
        </span>
    );
}

function PhotoBlock({ row }: { row: AnalysisLogRow }) {
    if (!row.has_image) {
        return <span className="text-muted-foreground">No</span>;
    }

    return (
        <span className="text-foreground">
            Yes
            {row.image_mime && (
                <span className="block text-muted-foreground">{row.image_mime}</span>
            )}
            {row.image_bytes != null && (
                <span className="block text-muted-foreground">
                    {formatBytes(row.image_bytes)}
                </span>
            )}
        </span>
    );
}

function UserBlock({ row }: { row: AnalysisLogRow }) {
    if (!row.user) {
        return <span className="text-muted-foreground">Guest</span>;
    }

    return (
        <>
            <span className="block truncate font-medium">{row.user.name}</span>
            <span className="block truncate text-muted-foreground">
                {row.user.email}
            </span>
        </>
    );
}

/** Compact analysis line for mobile summary (verdict + truncated hint). */
function AnalysisMobileSummary({ row }: { row: AnalysisLogRow }) {
    if (!row.analysis_result) {
        return <span className="text-muted-foreground">—</span>;
    }

    const ar = row.analysis_result;

    return (
        <div className="space-y-1.5">
            <span
                className={cn(
                    'inline-flex rounded-md border px-2 py-0.5 text-xs font-medium',
                    verdictBadgeClass(ar.verdict),
                )}
            >
                {normalizeVerdict(ar.verdict)}
            </span>
            <p className="line-clamp-2 text-xs leading-snug text-foreground/90">
                {ar.verdict_hint}
            </p>
        </div>
    );
}

/** Shared “more details” body: full analysis text + photo / user / IP. */
function ExpandedLogDetails({ row }: { row: AnalysisLogRow }) {
    const ar = row.analysis_result;

    return (
        <div className="space-y-4">
            {ar && (
                <div className="space-y-2 rounded-md border border-border/60 bg-background/60 p-3 text-xs">
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        Analysis details
                    </p>
                    <p className="text-foreground">
                        <span className="text-muted-foreground">Confidence: </span>
                        {ar.confidence}%
                        {ar.confidence_hint ? (
                            <span className="text-muted-foreground">
                                {' '}
                                · {ar.confidence_hint}
                            </span>
                        ) : null}
                    </p>
                    <p className="whitespace-pre-wrap text-foreground/90">
                        {ar.explanation}
                    </p>
                </div>
            )}

            <div className="grid gap-4 text-xs sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        Photo
                    </p>
                    <div className="mt-0.5">
                        <PhotoBlock row={row} />
                    </div>
                </div>
                <div>
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        User
                    </p>
                    <div className="mt-0.5">
                        <UserBlock row={row} />
                    </div>
                </div>
                <div>
                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        IP
                    </p>
                    <p className="mt-0.5 break-all font-mono text-muted-foreground">
                        {row.ip_address ?? '—'}
                    </p>
                </div>
            </div>
        </div>
    );
}

function LogCardMobile({
    row,
    onDelete,
}: {
    row: AnalysisLogRow;
    onDelete: () => void;
}) {
    return (
        <div className="rounded-lg border border-border bg-muted/20 p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1 space-y-2">
                    <div>
                        <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            Time
                        </p>
                        <p className="break-words text-sm leading-snug">
                            {row.created_at
                                ? new Date(row.created_at).toLocaleString()
                                : '—'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                        <div className="min-w-0">
                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                Mode
                            </p>
                            <p className="font-mono text-sm leading-none">
                                {row.mode}
                            </p>
                        </div>
                        <div className="shrink-0 text-right">
                            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                Request
                            </p>
                            <div className="mt-0.5 flex justify-end">
                                <RequestBadge row={row} />
                            </div>
                        </div>
                    </div>

                    <div>
                        <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            Analysis
                        </p>
                        <div className="mt-1">
                            <AnalysisMobileSummary row={row} />
                        </div>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    aria-label="Delete log entry"
                    onClick={onDelete}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            <Collapsible className="group mt-3 border-t border-border/80 pt-2">
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-9 w-full justify-between gap-2 px-2 text-muted-foreground hover:text-foreground"
                    >
                        <span className="text-sm font-medium">
                            View more details
                        </span>
                        <ChevronDown className="size-4 shrink-0 transition-transform duration-200 group-data-[state=open]:rotate-180" />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent className="space-y-3 overflow-hidden pt-1">
                    <ExpandedLogDetails row={row} />
                </CollapsibleContent>
            </Collapsible>
        </div>
    );
}

export default function AdminLogs({ logs }: PageProps) {
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [expandedRowId, setExpandedRowId] = useState<number | null>(null);

    const confirmDeleteLog = () => {
        if (pendingDeleteId == null) {
            return;
        }

        setDeleting(true);
        const removedId = pendingDeleteId;
        router.delete(destroy.url(pendingDeleteId), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: () => {
                toast.success('Log entry deleted.');
                setPendingDeleteId(null);
                setExpandedRowId((open) =>
                    open === removedId ? null : open,
                );
            },
            onError: () => {
                toast.error('Could not delete this entry. Try again.');
            },
        });
    };

    return (
        <>
            <Head title="Analysis activity" />
            <div className="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-6 p-4 pb-16">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Analysis activity
                    </h1>
                    <p className="mt-1 text-pretty text-sm text-muted-foreground">
                        Requests from the public home page (text, URL, or image).
                        Use this to see who analyzed what and whether it
                        succeeded.
                    </p>
                </div>

                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle>Recent requests</CardTitle>
                        <CardDescription>
                            Newest first. Logged when someone runs “Analyze” on{' '}
                            <span className="font-medium text-foreground">/</span>
                            .
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-3 md:hidden">
                            {logs.data.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No analysis requests yet. Run a check on the
                                    home page to see entries here.
                                </p>
                            ) : (
                                logs.data.map((row) => (
                                    <LogCardMobile
                                        key={row.id}
                                        row={row}
                                        onDelete={() =>
                                            setPendingDeleteId(row.id)
                                        }
                                    />
                                ))
                            )}
                        </div>

                        <div className="hidden min-w-0 rounded-lg border border-border md:block">
                            <table className="w-full table-fixed text-left text-sm">
                                <thead className="border-b border-border bg-muted/40">
                                    <tr>
                                        <th className="w-[22%] px-3 py-2 font-medium">
                                            Time
                                        </th>
                                        <th className="w-[10%] px-3 py-2 font-medium">
                                            Mode
                                        </th>
                                        <th className="w-[14%] px-3 py-2 font-medium">
                                            Request
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Analysis
                                        </th>
                                        <th className="w-10 px-1 py-2 text-center font-medium">
                                            <span className="sr-only">
                                                More details
                                            </span>
                                        </th>
                                        <th className="w-12 px-2 py-2 text-right font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-3 py-8 text-center text-muted-foreground"
                                            >
                                                No analysis requests yet. Run a
                                                check on the home page to see
                                                entries here.
                                            </td>
                                        </tr>
                                    ) : (
                                        logs.data.map((row) => {
                                            const isExpanded =
                                                expandedRowId === row.id;

                                            return (
                                                <Fragment key={row.id}>
                                                    <tr
                                                        className={cn(
                                                            'border-b border-border/80',
                                                            isExpanded &&
                                                                'bg-muted/15',
                                                        )}
                                                    >
                                                        <td className="px-3 py-2 align-top text-muted-foreground">
                                                            <span className="line-clamp-2 break-words text-xs leading-snug">
                                                                {row.created_at
                                                                    ? new Date(
                                                                          row.created_at,
                                                                      ).toLocaleString()
                                                                    : '—'}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                                            {row.mode}
                                                        </td>
                                                        <td className="px-3 py-2 align-top">
                                                            <RequestBadge
                                                                row={row}
                                                            />
                                                        </td>
                                                        <td className="min-w-0 px-3 py-2 align-top">
                                                            <AnalysisMobileSummary
                                                                row={row}
                                                            />
                                                        </td>
                                                        <td className="px-1 py-2 align-middle text-center">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                                                                aria-expanded={
                                                                    isExpanded
                                                                }
                                                                aria-label={
                                                                    isExpanded
                                                                        ? 'Hide details'
                                                                        : 'View more details'
                                                                }
                                                                title={
                                                                    isExpanded
                                                                        ? 'Hide details'
                                                                        : 'View more details'
                                                                }
                                                                onClick={() =>
                                                                    setExpandedRowId(
                                                                        (
                                                                            prev,
                                                                        ) =>
                                                                            prev ===
                                                                            row.id
                                                                                ? null
                                                                                : row.id,
                                                                    )
                                                                }
                                                            >
                                                                <ChevronDown
                                                                    className={cn(
                                                                        'size-4 transition-transform duration-200',
                                                                        isExpanded &&
                                                                            'rotate-180',
                                                                    )}
                                                                />
                                                            </Button>
                                                        </td>
                                                        <td className="px-2 py-2 align-middle text-right">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="icon"
                                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                aria-label="Delete log entry"
                                                                title="Delete log entry"
                                                                onClick={() =>
                                                                    setPendingDeleteId(
                                                                        row.id,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                    {isExpanded && (
                                                        <tr className="border-b border-border/80 bg-muted/25">
                                                            <td
                                                                colSpan={6}
                                                                className="px-4 py-3"
                                                            >
                                                                <p className="mb-3 text-xs font-medium text-muted-foreground">
                                                                    Full details
                                                                </p>
                                                                <ExpandedLogDetails
                                                                    row={row}
                                                                />
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                {logs.from != null &&
                                logs.to != null &&
                                logs.total > 0
                                    ? `Showing ${logs.from}–${logs.to} of ${logs.total}`
                                    : logs.total === 0
                                      ? 'No entries'
                                      : `Page ${logs.current_page} of ${logs.last_page}`}
                            </p>
                            <div className="flex gap-2">
                                {logs.prev_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={logs.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Previous
                                    </Button>
                                )}
                                {logs.next_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={logs.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Next
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={pendingDeleteId !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setPendingDeleteId(null);
                    }
                }}
            >
                <DialogContent
                    showCloseButton={!deleting}
                    onPointerDownOutside={(e) =>
                        deleting ? e.preventDefault() : undefined
                    }
                    onEscapeKeyDown={(e) =>
                        deleting ? e.preventDefault() : undefined
                    }
                >
                    <DialogHeader>
                        <DialogTitle>Delete log entry?</DialogTitle>
                        <DialogDescription>
                            This removes the row from analysis activity. This
                            action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={deleting}
                            onClick={() => setPendingDeleteId(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deleting}
                            onClick={confirmDeleteLog}
                        >
                            {deleting ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminLogs.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard.url(),
        },
        {
            title: 'Analysis activity',
            href: adminLogsIndex.url(),
        },
    ],
};
