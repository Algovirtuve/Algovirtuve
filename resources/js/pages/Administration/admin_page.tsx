import { Head, router, usePage } from '@inertiajs/react';
import { viewAdminPage } from '@/actions/App/Http/Controllers/Administration/admin_controller';
import { viewRequests } from '@/actions/App/Http/Controllers/Administration/requests_controller';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

export default function AdminPage({
    requests_count,
    rejected_requests_count,
    waiting_requests_count,
    approved_requests_count,
    users_count,
}: {
    requests_count: number;
    rejected_requests_count: number;
    waiting_requests_count: number;
    approved_requests_count: number;
    users_count: number;
}) {
    const { auth } = usePage().props;

    const onViewRequests = () => {
        router.visit(viewRequests().url);
    };

    return (
        <>
            <Head title="Admin" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <Heading
                        title="Admin page"
                        description="Review platform activity and handle recipe requests."
                    />

                    {auth.is_administrator && (
                        <Button onClick={onViewRequests}>View requests</Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Total requests</CardTitle>
                            <CardDescription>
                                All submitted requests.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {requests_count}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Waiting</CardTitle>
                            <CardDescription>Awaiting review.</CardDescription>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {waiting_requests_count}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Approved</CardTitle>
                            <CardDescription>
                                Approved requests.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {approved_requests_count}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rejected</CardTitle>
                            <CardDescription>
                                Declined requests.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {rejected_requests_count}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Users</CardTitle>
                            <CardDescription>
                                Total registered users.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-3xl font-semibold">
                            {users_count}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminPage.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Admin',
            href: viewAdminPage().url,
        },
    ],
};
