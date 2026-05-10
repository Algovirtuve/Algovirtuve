import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';
import { viewAdminPage } from '@/actions/App/Http/Controllers/Administration/admin_controller';
import {
    approveRequest,
    declineRequest,
    viewRequests,
} from '@/actions/App/Http/Controllers/Administration/requests_controller';

type RequestDetails = {
    id: number;
    date: string | null;
    administrator_id: number | null;
    user: {
        id: number | null;
        username: string | null;
        email: string | null;
    };
    recipe: null | {
        id: number;
        title: string;
        instructions: string;
        status: string;
        status_label: string;
        owner: {
            id: number | null;
            username: string | null;
            email: string | null;
        };
    };
};

export default function RequestPage({ request }: { request: RequestDetails }) {
    const canDecide = request.recipe?.status === 'waiting_for_review';

    const onReject = () => {
        router.patch(declineRequest(request.id).url);
    };

    const onApprove = () => {
        router.patch(approveRequest(request.id).url);
    };

    return (
        <>
            <Head title={`Request #${request.id}`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title={`Request #${request.id}`}
                        description="Review the request and approve or reject it."
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        {request.recipe?.status_label && (
                            <Badge variant="secondary">
                                {request.recipe.status_label}
                            </Badge>
                        )}

                        <Button
                            variant="secondary"
                            onClick={() => router.visit(viewRequests().url)}
                        >
                            Back to requests
                        </Button>

                        <Button
                            variant="destructive"
                            disabled={!canDecide}
                            onClick={onReject}
                        >
                            Reject
                        </Button>
                        <Button disabled={!canDecide} onClick={onApprove}>
                            Approve
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Request details</CardTitle>
                        <CardDescription>
                            Submitted by {request.user.username ?? 'Unknown'}
                            {request.date ? ` on ${request.date}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div>
                            <div className="font-medium">User email</div>
                            <div className="text-muted-foreground">
                                {request.user.email ?? '—'}
                            </div>
                        </div>

                        <Separator />

                        {request.recipe ? (
                            <div className="space-y-4">
                                <div>
                                    <div className="font-medium">Recipe</div>
                                    <div className="text-muted-foreground">
                                        {request.recipe.title}
                                    </div>
                                </div>

                                <div>
                                    <div className="font-medium">
                                        Recipe owner
                                    </div>
                                    <div className="text-muted-foreground">
                                        {request.recipe.owner.username ?? '—'}
                                        {request.recipe.owner.email
                                            ? ` · ${request.recipe.owner.email}`
                                            : ''}
                                    </div>
                                </div>

                                <div>
                                    <div className="font-medium">
                                        Instructions
                                    </div>
                                    <div className="whitespace-pre-wrap text-muted-foreground">
                                        {request.recipe.instructions}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-muted-foreground">
                                Recipe not found.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

RequestPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Admin',
            href: viewAdminPage().url,
        },
        {
            title: 'Requests',
            href: viewRequests().url,
        },
        {
            title: 'Request',
            href: '#',
        },
    ],
};
