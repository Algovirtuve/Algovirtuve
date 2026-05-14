import { Head, Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as onToolPage, store as onToolStore } from '@/routes/tools';

type ToolTypeOption = {
    value: string;
    label: string;
};

export default function ToolCreationPage({
    tool_types,
}: {
    tool_types: ToolTypeOption[];
}) {
    const { errors } = usePage<{ errors?: Record<string, string[]> }>().props;

    return (
        <>
            <Head title="Create tool" />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm tracking-[0.24em] text-muted-foreground uppercase">
                        Tools
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Create a new tool
                    </h1>
                </div>
                <TextLink href={onToolPage()}>
                    <ArrowLeft />
                    Back to tools
                </TextLink>
            </div>

            <Form {...onToolStore.form()} className="grid max-w-2xl gap-6">
                {({ processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="type">Tool type</Label>
                            <Select name="type" defaultValue="">
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Choose a tool type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {tool_types.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors?.type?.[0]} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                className="min-w-[10rem]"
                                disabled={processing}
                                type="submit"
                            >
                                <Plus />
                                Create tool
                            </Button>
                            <Link
                                href={onToolPage()}
                                className="text-sm text-muted-foreground hover:text-primary"
                            >
                                Cancel
                            </Link>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
