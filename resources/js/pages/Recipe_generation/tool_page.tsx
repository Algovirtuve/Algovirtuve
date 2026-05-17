import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ToolItem = {
    id: number;
    type: string;
    type_label: string;
};

export default function ToolPage({ tools }: { tools: ToolItem[] }) {
    const [toolToRemove, setToolToRemove] = useState<ToolItem | null>(null);

    const startToolDeletion = (tool: ToolItem) => {
        setToolToRemove(tool);
    };

    const startToolCreation = () => {
        router.visit('/tools/create');
    };

    const confirmToolDeletion = () => {
        if (toolToRemove == null) {
            return;
        }

        router.delete(`/tools/${toolToRemove.id}`);
        setToolToRemove(null);
    };

    const cancelToolDeletion = () => {
        setToolToRemove(null);
    };

    return (
        <>
            <Head title="Tools" />

            <div className="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm tracking-[0.24em] text-muted-foreground uppercase">
                            Tools
                        </p>
                        <CardTitle className="mt-2 text-3xl">
                            My tools
                        </CardTitle>
                    </div>
                    <Button onClick={() => startToolCreation()}>
                        <Plus />
                        Add tool
                    </Button>
                </div>

                {tools.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                You have not added any tools yet. Create one to
                                start building your recipe profile.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {tools.map((tool) => (
                            <Card key={tool.id}>
                                <CardHeader>
                                    <CardTitle>{tool.type_label}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Tool type: {tool.type_label}
                                    </p>
                                </CardContent>
                                <CardFooter className="justify-end gap-2">
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => startToolDeletion(tool)}
                                    >
                                        Remove
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {toolToRemove ? (
                <Dialog>
                    <DialogHeader>
                        <DialogTitle>Confirm removal</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove{' '}
                            <strong>{toolToRemove.type_label}</strong> from your
                            tools?
                        </DialogDescription>
                    </DialogHeader>

                    <Button
                        variant="outline"
                        onClick={() => cancelToolDeletion()}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => confirmToolDeletion()}
                    >
                        Delete tool
                    </Button>
                </Dialog>
            ) : null}
        </>
    );
}
