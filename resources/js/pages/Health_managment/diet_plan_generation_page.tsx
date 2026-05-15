import { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, generate } from '@/routes/diet';

type EnumOption = { value: string; label: string };
type MacroelementOption = { id: number; title: string; measurement: string };
type SelectedMacroelement = { id: number; target_kcal: number };

type TempState = {
    macroelements?: SelectedMacroelement[];
    diet_type?: string | null;
    day_calorie_limit?: number | null;
};

type Step = 'macroelements' | 'diet_type' | 'calorie_limit' | 'review';

export default function diet_plan_generation_page({
    macroelements,
    dietTypes,
    tempState = {},
}: {
    macroelements: MacroelementOption[];
    dietTypes: EnumOption[];
    tempState?: TempState;
}) {
    const [step, setStep] = useState<Step>('macroelements');
    const [clientError, setClientError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, clearErrors, transform } =
        useForm({
            macroelements: (tempState.macroelements ??
                []) as SelectedMacroelement[],
            diet_type: (tempState.diet_type ?? '') as string,
            day_calorie_limit: (tempState.day_calorie_limit ?? '') as
                | number
                | '',
        });

    const macroTitleById = useMemo(() => {
        return new Map(macroelements.map((m) => [m.id, m.title] as const));
    }, [macroelements]);

    const validate = (targetStep: Step): boolean => {
        setClientError(null);

        if (targetStep === 'macroelements') {
            if (!data.macroelements || data.macroelements.length === 0) {
                setClientError('Pick at least one macroelement.');
                return false;
            }

            const hasInvalid = data.macroelements.some(
                (m) => !m.id || !m.target_kcal || m.target_kcal <= 0,
            );

            if (hasInvalid) {
                setClientError('Each selected macroelement needs target kcal.');
                return false;
            }
        }

        if (targetStep === 'diet_type') {
            if (!data.diet_type) {
                setClientError('Select a diet type.');
                return false;
            }
        }

        if (targetStep === 'calorie_limit') {
            if (
                !data.day_calorie_limit ||
                Number(data.day_calorie_limit) <= 0
            ) {
                setClientError('Insert a valid daily calorie limit.');
                return false;
            }
        }

        return true;
    };

    const toggleMacroelement = (macroId: number, isChecked: boolean) => {
        const current = data.macroelements ?? [];

        if (isChecked) {
            if (current.some((m) => m.id === macroId)) {
                return;
            }

            setData('macroelements', [
                ...current,
                { id: macroId, target_kcal: 1 },
            ]);
            return;
        }

        setData(
            'macroelements',
            current.filter((m) => m.id !== macroId),
        );
    };

    const setMacroTarget = (macroId: number, targetKcal: number) => {
        setData(
            'macroelements',
            (data.macroelements ?? []).map((m) =>
                m.id === macroId ? { ...m, target_kcal: targetKcal } : m,
            ),
        );
    };

    const insertToTempMacros = () => ({
        macroelements: data.macroelements,
    });

    const insertToTempType = () => ({
        diet_type: data.diet_type,
    });

    const insertToTempCalorie = () => ({
        day_calorie_limit: data.day_calorie_limit,
    });

    const onNextStepClick = (nextStep: Step) => {
        setStep(nextStep);
    };

    const onMacroelementsSubmit = () => {
        clearErrors();
        if (!validate('macroelements')) {
            return;
        }

        onNextStepClick('diet_type');
    };

    const onDietTypeSelect = (value: string) => {
        setData('diet_type', value);
        clearErrors();
    };

    const onDietTypeSubmit = () => {
        clearErrors();
        if (!validate('diet_type')) {
            return;
        }

        onNextStepClick('calorie_limit');
    };

    const onCalorieLimitInsert = () => {
        clearErrors();
        if (!validate('calorie_limit')) {
            return;
        }

        setData('day_calorie_limit', Number(data.day_calorie_limit));
        onNextStepClick('review');
    };

    const onSubmitDataClick = () => {
        clearErrors();

        const payload = {
            ...insertToTempMacros(),
            ...insertToTempType(),
            ...insertToTempCalorie(),
        };

        transform(() => payload);

        post(generate.url(), {
            preserveScroll: true,
            onFinish: () => transform((current) => current),
        });
    };

    const onCalorieLimitPrevious = () => {
        onNextStepClick('diet_type');
    };

    const onDietTypePrevious = () => {
        onNextStepClick('macroelements');
    };

    const onMacroelementsPrevious = () => {
        onNextStepClick('macroelements');
    };

    const selectedIds = useMemo(
        () => new Set((data.macroelements ?? []).map((m) => m.id)),
        [data.macroelements],
    );

    return (
        <div className="space-y-6 p-4">
            <Heading
                title="Generate diet plan"
                description="Follow the steps to generate a diet plan."
            />

            {clientError ? <InputError message={clientError} /> : null}

            {step === 'macroelements' ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Step 1 — Macroelements</CardTitle>
                        <CardDescription>
                            Select macroelements and set target grams for each.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3">
                            {macroelements.map((m) => {
                                const checked = selectedIds.has(m.id);
                                const selected = (
                                    data.macroelements ?? []
                                ).find((x) => x.id === m.id);

                                return (
                                    <div
                                        key={m.id}
                                        className="flex flex-col gap-2 rounded-md border p-3"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(v) =>
                                                    toggleMacroelement(
                                                        m.id,
                                                        Boolean(v),
                                                    )
                                                }
                                                id={`macro-${m.id}`}
                                            />
                                            <Label htmlFor={`macro-${m.id}`}>
                                                {m.title} ({m.measurement})
                                            </Label>
                                        </div>

                                        {checked ? (
                                            <div className="grid gap-2 sm:max-w-sm">
                                                <Label
                                                    htmlFor={`macro-kcal-${m.id}`}
                                                >
                                                    Target grams
                                                </Label>
                                                <Input
                                                    id={`macro-kcal-${m.id}`}
                                                    type="number"
                                                    min={1}
                                                    value={
                                                        selected?.target_kcal ??
                                                        1
                                                    }
                                                    onChange={(event) =>
                                                        setMacroTarget(
                                                            m.id,
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>

                        <InputError message={errors.macroelements as string} />

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                onClick={onMacroelementsSubmit}
                                disabled={processing}
                            >
                                Next step
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            {step === 'diet_type' ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Step 2 — Diet type</CardTitle>
                        <CardDescription>
                            Choose the diet type for recipe selection.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2 sm:max-w-sm">
                            <Label>Diet type</Label>
                            <Select
                                value={data.diet_type}
                                onValueChange={onDietTypeSelect}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select diet type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {dietTypes.map((d) => (
                                        <SelectItem
                                            key={d.value}
                                            value={d.value}
                                        >
                                            {d.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.diet_type} />
                        </div>

                        <div className="flex justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onDietTypePrevious}
                            >
                                Previous
                            </Button>
                            <Button
                                type="button"
                                onClick={onDietTypeSubmit}
                                disabled={processing || !data.diet_type}
                            >
                                Next step
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            {step === 'calorie_limit' ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Step 3 — Daily calorie limit</CardTitle>
                        <CardDescription>
                            Insert your daily calorie limit. We will split it as
                            20% breakfast, 50% lunch, 30% dinner.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2 sm:max-w-sm">
                            <Label htmlFor="day-calorie-limit">
                                Daily calorie limit
                            </Label>
                            <Input
                                id="day-calorie-limit"
                                type="number"
                                min={1}
                                value={data.day_calorie_limit}
                                onChange={(event) =>
                                    setData(
                                        'day_calorie_limit',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.day_calorie_limit} />
                        </div>

                        <div className="flex justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCalorieLimitPrevious}
                            >
                                Previous
                            </Button>
                            <Button
                                type="button"
                                onClick={onCalorieLimitInsert}
                                disabled={processing}
                            >
                                Next step
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            {step === 'review' ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Step 4 — Review</CardTitle>
                        <CardDescription>
                            Confirm your selections and generate the plan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <div className="text-sm">
                                <span className="font-medium">Diet type:</span>{' '}
                                {data.diet_type || '—'}
                            </div>
                            <div className="text-sm">
                                <span className="font-medium">
                                    Day calorie limit:
                                </span>{' '}
                                {data.day_calorie_limit || '—'}
                            </div>
                            <div className="text-sm">
                                <span className="font-medium">
                                    Selected macroelements:
                                </span>
                                <ul className="ml-4 list-disc">
                                    {(data.macroelements ?? []).map((m) => (
                                        <li key={m.id}>
                                            {macroTitleById.get(m.id) ?? m.id} —{' '}
                                            {m.target_kcal} g
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>

                        <div className="flex justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onMacroelementsPrevious}
                            >
                                Edit macroelements
                            </Button>
                            <Button
                                type="button"
                                onClick={onSubmitDataClick}
                                disabled={processing}
                            >
                                Generate
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : null}
        </div>
    );
}

diet_plan_generation_page.layout = {
    breadcrumbs: [
        {
            title: 'Diet plan',
            href: index(),
        },
        {
            title: 'Generate',
            href: index(),
        },
    ],
};
