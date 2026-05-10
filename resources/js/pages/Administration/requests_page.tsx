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
import { dashboard } from '@/routes';
import { viewAdminPage } from '@/actions/App/Http/Controllers/Administration/admin_controller';
import { viewRequest } from '@/actions/App/Http/Controllers/Administration/requests_controller';

type RequestListItem = {
    id: number;
    date: string | null;
    administrator_id: number | null;
    user: {
        id: number | null;
        username: string | null;
        email: string | null;
    };
    recipe: {
        id: number | null;
        title: string | null;
        status: string | null;
        status_label: string | null;
    };
};

export default function RequestsPage({
    requests,
}: {
    requests: RequestListItem[];
}) {
    const onViewRequest = (requestId: number) => {
        router.visit(viewRequest(requestId).url);
    };

    return (
        <>
            <Head title="Requests" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Requests"
                    description="Browse and review all recipe requests."
                />

                {requests.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle>No requests</CardTitle>
                            <CardDescription>
                                Requests will appear here once users submit
                                them.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {requests.map((request) => (
                            <Card key={request.id}>
                                <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="space-y-1">
                                        <CardTitle className="text-base">
                                            {request.recipe.title ??
                                                `Request #${request.id}`}
                                        </CardTitle>
                                        <CardDescription>
                                            {request.user.username ??
                                                'Unknown user'}
                                            {request.date
                                                ? ` · ${request.date}`
                                                : ''}
                                        </CardDescription>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        {request.recipe.status_label && (
                                            <Badge variant="secondary">
                                                {request.recipe.status_label}
                                            </Badge>
                                        )}
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                onViewRequest(request.id)
                                            }
                                        >
                                            View
                                        </Button>
                                    </div>
                                </CardHeader>

                                <CardContent className="text-sm text-muted-foreground">
                                    {request.user.email ?? ''}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

RequestsPage.layout = {
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
            href: '#',
        },
    ],
};
