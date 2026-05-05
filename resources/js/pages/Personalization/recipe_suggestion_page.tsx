import { Head, Link, router } from '@inertiajs/react';
import {
    animate,
    AnimatePresence,
    motion,
    useMotionTemplate,
    useMotionValue,
    useTransform,
} from 'motion/react';
import type { PanInfo } from 'motion/react';
import { startTransition, useRef, useState } from 'react';
import {
    dislikeSuggestion,
    likeSuggestion,
    suggestions,
} from '@/actions/App/Http/Controllers/Personalization/personalization_controller';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as preferences } from '@/routes/preferences';

type Suggestion = {
    id: number;
    preference_status: 'awaiting' | 'liked' | 'disliked';
    status_label: string;
    generation_date: string | null;
    score: number;
    missing_ingredients_count: number;
    matched_tools_count: number;
    available_ingredients_count: number;
    recipe: {
        id: number;
        title: string;
        image_url: string | null;
        instructions: string;
        preparation_time: string;
        servings: number;
        difficulty: string;
        difficulty_label: string;
        calorie_intake: number;
        status: string;
        status_label: string;
        diet_type: string;
        diet_type_label: string;
        meal: string;
        meal_label: string;
        ingredients: Array<{
            id: number;
            title: string;
            importance: boolean;
        }>;
        tools: Array<{
            id: number;
            title: string;
        }>;
    };
};

const swipeThreshold = 0.58;
const deckLayers = [{ scale: 0.92, y: 34, opacity: 0.35 }];
const deckFrameClass =
    'h-[min(72vh,52rem)] min-h-[30rem] xl:h-[calc(100vh-9rem)]';
const panelCardClass =
    'rounded-3xl border-white/10 bg-white/4 py-0 backdrop-blur-xl';
const panelTitleClass =
    'text-xs font-bold tracking-widest text-muted-foreground uppercase';

export default function RecipeSuggestionPage({
    suggestion,
    remaining_suggestions_count,
}: {
    suggestion: Suggestion | null;
    remaining_suggestions_count: number;
}) {
    const cardRef = useRef<HTMLDivElement | null>(null);
    const queuedSuggestionRef = useRef<{
        ready: boolean;
        suggestion: Suggestion | null;
    }>({
        ready: false,
        suggestion: null,
    });
    const exitCompletedRef = useRef(false);
    const [decision, setDecision] = useState<'liked' | 'disliked' | null>(null);
    const [currentSuggestion, setCurrentSuggestion] =
        useState<Suggestion | null>(suggestion);
    const x = useMotionValue(0);
    const rotate = useTransform(x, [-560, 0, 560], [-8, 0, 8]);
    const greenGlowOpacity = useTransform(x, [0, 150, 340], [0, 0.35, 1]);
    const redGlowOpacity = useTransform(x, [-340, -150, 0], [1, 0.35, 0]);
    const likeOpacity = useTransform(x, [0, 150, 340], [0, 0.55, 1]);
    const dislikeOpacity = useTransform(x, [-340, -150, 0], [1, 0.55, 0]);
    const cardShadow = useMotionTemplate`0 36px 110px rgba(0, 0, 0, 0.34)`;
    const highlightedIngredients = currentSuggestion?.recipe.ingredients.slice(
        0,
        6,
    );
    const importantIngredients = currentSuggestion?.recipe.ingredients.filter(
        (ingredient) => ingredient.importance,
    );
    const visibleTools = currentSuggestion?.recipe.tools.slice(0, 4);
    const fitStats = [
        {
            label: 'Have',
            value: currentSuggestion?.available_ingredients_count ?? 0,
        },
        {
            label: 'Need',
            value: currentSuggestion?.missing_ingredients_count ?? 0,
        },
        {
            label: 'Tools',
            value: currentSuggestion?.matched_tools_count ?? 0,
        },
        {
            label: 'Deck',
            value: remaining_suggestions_count,
        },
    ];
    const recipeFacts = currentSuggestion
        ? [
              {
                  label: 'Time',
                  value: currentSuggestion.recipe.preparation_time,
              },
              {
                  label: 'Difficulty',
                  value: currentSuggestion.recipe.difficulty_label,
              },
              {
                  label: 'Servings',
                  value: String(currentSuggestion.recipe.servings),
              },
              {
                  label: 'Energy',
                  value: `${currentSuggestion.recipe.calorie_intake} kcal`,
              },
          ]
        : [];

    function exitTarget(direction: 'liked' | 'disliked'): number {
        const cardWidth = cardRef.current?.getBoundingClientRect().width ?? 0;
        const viewportWidth =
            typeof window === 'undefined' ? 1280 : window.innerWidth;

        return direction === 'liked'
            ? viewportWidth + cardWidth
            : -(viewportWidth + cardWidth);
    }

    function showNextSuggestionWhenReady() {
        if (!exitCompletedRef.current || !queuedSuggestionRef.current.ready) {
            return;
        }

        exitCompletedRef.current = false;
        const nextSuggestion = queuedSuggestionRef.current.suggestion;

        queuedSuggestionRef.current = {
            ready: false,
            suggestion: null,
        };

        setCurrentSuggestion(nextSuggestion);
    }

    function submitDecision(
        nextDecision: 'liked' | 'disliked',
        options?: { releaseVelocity?: number; useFlyAway?: boolean },
    ) {
        if (currentSuggestion === null || decision !== null) {
            return;
        }

        const currentSuggestionId = currentSuggestion.id;

        queuedSuggestionRef.current = {
            ready: false,
            suggestion: null,
        };
        exitCompletedRef.current = false;

        startTransition(() => {
            setDecision(nextDecision);
        });

        router.patch(
            nextDecision === 'liked'
                ? likeSuggestion.url(currentSuggestionId)
                : dislikeSuggestion.url(currentSuggestionId),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['suggestion', 'remaining_suggestions_count', 'flash'],
                onSuccess: (page) => {
                    queuedSuggestionRef.current = {
                        ready: true,
                        suggestion: (page.props.suggestion ??
                            null) as Suggestion | null,
                    };

                    showNextSuggestionWhenReady();
                },
                onError: () => {
                    queuedSuggestionRef.current = {
                        ready: false,
                        suggestion: null,
                    };
                    exitCompletedRef.current = false;

                    animate(x, 0, {
                        type: 'spring',
                        stiffness: 320,
                        damping: 28,
                        mass: 0.7,
                        velocity: x.getVelocity(),
                    });
                    setDecision(null);
                },
            },
        );

        animate(x, exitTarget(nextDecision), {
            ...(options?.useFlyAway
                ? {
                      type: 'tween' as const,
                      ease: [0.55, 0.06, 0.68, 0.19],
                      duration: 0.9,
                  }
                : {
                      type: 'spring' as const,
                      stiffness: 220,
                      damping: 24,
                      mass: 0.8,
                      velocity: options?.releaseVelocity ?? x.getVelocity(),
                  }),
            onComplete: () => {
                exitCompletedRef.current = true;
                showNextSuggestionWhenReady();
            },
        });
    }

    function handleDragEnd(
        _: MouseEvent | TouchEvent | PointerEvent,
        info: PanInfo,
    ) {
        const width = cardRef.current?.getBoundingClientRect().width ?? 1;
        const swipeRatio = info.offset.x / width;

        if (swipeRatio >= swipeThreshold) {
            submitDecision('liked', {
                releaseVelocity: info.velocity.x,
                useFlyAway: true,
            });

            return;
        }

        if (swipeRatio <= -swipeThreshold) {
            submitDecision('disliked', {
                releaseVelocity: info.velocity.x,
                useFlyAway: true,
            });

            return;
        }

        animate(x, 0, {
            type: 'spring',
            stiffness: 320,
            damping: 28,
            mass: 0.7,
            velocity: x.getVelocity(),
        });
    }

    return (
        <>
            <Head title="Recipe Suggestions" />

            <div className="min-h-[calc(100vh-5rem)] overflow-hidden bg-linear-to-b from-amber-50/10 via-background to-emerald-50/10 px-4 pt-2 pb-6 md:px-6 md:pt-3 md:pb-8 dark:from-amber-500/10 dark:via-background dark:to-emerald-500/10">
                {currentSuggestion === null ? (
                    <Card className="mx-auto mt-6 max-w-2xl border-dashed bg-background/70 backdrop-blur-xl">
                        <CardHeader>
                            <CardTitle>No suggestions available</CardTitle>
                            <CardDescription>
                                The current deck is empty. Refresh after seeding
                                more recipes or clear older decisions to
                                repopulate the stack.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="flex gap-3">
                            <Button className="rounded-full" asChild>
                                <Link href={suggestions()} prefetch>
                                    Refresh suggestions
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="rounded-full"
                                asChild
                            >
                                <Link href={preferences()} prefetch>
                                    Open preferences
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                ) : (
                    <div className="mx-auto grid max-w-screen-2xl gap-4 xl:grid-cols-12 xl:items-start xl:gap-6">
                        <aside className="order-2 space-y-4 xl:order-1 xl:col-span-2">
                            <Card className={panelCardClass}>
                                <CardContent className="space-y-4 px-4 py-5">
                                    <p className={panelTitleClass}>Quick fit</p>

                                    <div className="grid grid-cols-2 gap-3">
                                        {fitStats.map((stat) => (
                                            <div
                                                key={stat.label}
                                                className="rounded-2xl border border-white/10 bg-white/4 p-3"
                                            >
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    {stat.label}
                                                </p>
                                                <p className="mt-2 text-2xl font-semibold text-white">
                                                    {stat.value}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className={panelCardClass}>
                                <CardContent className="space-y-3 px-4 py-5">
                                    <p className={panelTitleClass}>
                                        Key ingredients
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {(importantIngredients?.length
                                            ? importantIngredients
                                            : highlightedIngredients
                                        )?.map((ingredient) => (
                                            <Badge
                                                key={ingredient.id}
                                                className="rounded-full border-white/10 bg-white/6 px-3 py-1.5 text-white hover:bg-white/6"
                                                variant={
                                                    ingredient.importance
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {ingredient.title}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>

                        <main className="order-1 xl:order-2 xl:col-span-8">
                            <div className="relative mx-auto w-full pt-1">
                                <motion.div
                                    aria-hidden
                                    className={`pointer-events-none absolute inset-x-5 top-6 z-0 rounded-3xl bg-emerald-500/40 blur-3xl ${deckFrameClass}`}
                                    style={{ opacity: greenGlowOpacity }}
                                />
                                <motion.div
                                    aria-hidden
                                    className={`pointer-events-none absolute inset-x-5 top-6 z-0 rounded-3xl bg-rose-500/40 blur-3xl ${deckFrameClass}`}
                                    style={{ opacity: redGlowOpacity }}
                                />

                                {deckLayers.map((layer, index) => (
                                    <div
                                        key={layer.y}
                                        aria-hidden
                                        className={`absolute inset-x-5 top-6 rounded-3xl border border-white/8 bg-white/3 shadow-2xl shadow-black/20 backdrop-blur-sm ${deckFrameClass}`}
                                        style={{
                                            transform: `translateY(${layer.y}px) scale(${layer.scale})`,
                                            opacity: layer.opacity,
                                            zIndex: index,
                                        }}
                                    >
                                        <div className="flex h-full items-end justify-center p-6">
                                            <div className="h-24 w-full rounded-3xl border border-dashed border-white/10 bg-white/2" />
                                        </div>
                                    </div>
                                ))}

                                <AnimatePresence
                                    mode="wait"
                                    initial={false}
                                    onExitComplete={() => {
                                        x.jump(0);
                                        setDecision(null);
                                    }}
                                >
                                    <motion.div
                                        key={currentSuggestion.id}
                                        ref={cardRef}
                                        drag="x"
                                        dragConstraints={{ left: 0, right: 0 }}
                                        dragElastic={0.18}
                                        dragMomentum={false}
                                        onDragEnd={handleDragEnd}
                                        style={{
                                            x,
                                            rotate,
                                            boxShadow: cardShadow,
                                        }}
                                        className="relative z-10 touch-pan-y overflow-hidden rounded-3xl outline-none"
                                        whileTap={{
                                            cursor: 'grabbing',
                                            scale: 0.995,
                                        }}
                                        whileDrag={{ scale: 1.01 }}
                                        initial={{
                                            opacity: 0,
                                            y: 36,
                                            scale: 0.965,
                                        }}
                                        animate={{
                                            opacity: 1,
                                            y: 0,
                                            scale: 1,
                                        }}
                                        exit={{
                                            opacity: 0,
                                            y: -18,
                                            scale: 0.985,
                                        }}
                                        transition={{
                                            opacity: {
                                                duration: 0.22,
                                                ease: 'easeOut',
                                            },
                                            y: {
                                                duration: 0.28,
                                                ease: 'easeOut',
                                            },
                                            scale: {
                                                duration: 0.28,
                                                ease: 'easeOut',
                                            },
                                        }}
                                    >
                                        <Card className="overflow-hidden rounded-3xl border-white/10 bg-zinc-950/95 p-0 shadow-none backdrop-blur-2xl">
                                            <div
                                                className={`relative overflow-hidden bg-muted ${deckFrameClass}`}
                                            >
                                                {currentSuggestion.recipe
                                                    .image_url ? (
                                                    <img
                                                        src={
                                                            currentSuggestion
                                                                .recipe
                                                                .image_url
                                                        }
                                                        alt={
                                                            currentSuggestion
                                                                .recipe.title
                                                        }
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex h-full items-center justify-center bg-linear-to-b from-amber-100/20 to-white/5 text-sm text-muted-foreground dark:from-amber-300/20 dark:to-white/5">
                                                        No image available
                                                    </div>
                                                )}

                                                <div className="absolute inset-0 bg-linear-to-t from-black via-black/30 to-black/5" />

                                                <motion.div
                                                    className="absolute top-5 left-5 rounded-full border border-emerald-300/60 bg-emerald-500/20 px-4 py-2 text-sm font-semibold tracking-widest text-emerald-100 backdrop-blur-md"
                                                    style={{
                                                        opacity: likeOpacity,
                                                    }}
                                                >
                                                    LIKE
                                                </motion.div>
                                                <motion.div
                                                    className="absolute top-5 right-5 rounded-full border border-rose-300/60 bg-rose-500/20 px-4 py-2 text-sm font-semibold tracking-widest text-rose-100 backdrop-blur-md"
                                                    style={{
                                                        opacity: dislikeOpacity,
                                                    }}
                                                >
                                                    NOPE
                                                </motion.div>

                                                <div className="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                                                    <div className="space-y-4 rounded-3xl border border-white/10 bg-black/40 p-5 shadow-2xl shadow-black/30 backdrop-blur-xl">
                                                        <div className="space-y-2">
                                                            <div className="flex flex-wrap gap-2">
                                                                <Badge className="rounded-full border-white/10 bg-white/10 text-white hover:bg-white/10">
                                                                    {
                                                                        currentSuggestion
                                                                            .recipe
                                                                            .meal_label
                                                                    }
                                                                </Badge>
                                                                <Badge className="rounded-full border-white/10 bg-white/10 text-white hover:bg-white/10">
                                                                    {
                                                                        currentSuggestion
                                                                            .recipe
                                                                            .diet_type_label
                                                                    }
                                                                </Badge>
                                                            </div>
                                                            <CardTitle className="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
                                                                {
                                                                    currentSuggestion
                                                                        .recipe
                                                                        .title
                                                                }
                                                            </CardTitle>
                                                        </div>

                                                        <p className="line-clamp-4 text-sm leading-6 text-white/80 sm:text-base">
                                                            {
                                                                currentSuggestion
                                                                    .recipe
                                                                    .instructions
                                                            }
                                                        </p>

                                                        <div className="grid grid-cols-2 gap-3 text-white sm:grid-cols-4">
                                                            {recipeFacts.map(
                                                                (fact) => (
                                                                    <div
                                                                        key={
                                                                            fact.label
                                                                        }
                                                                        className="rounded-2xl border border-white/10 bg-white/6 p-3"
                                                                    >
                                                                        <div className="mb-2 text-xs tracking-wider text-white/60 uppercase">
                                                                            {
                                                                                fact.label
                                                                            }
                                                                        </div>
                                                                        <p className="text-sm font-medium">
                                                                            {
                                                                                fact.value
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </Card>
                                    </motion.div>
                                </AnimatePresence>
                            </div>
                        </main>

                        <aside className="order-3 space-y-4 xl:col-span-2">
                            <Card className={panelCardClass}>
                                <CardContent className="space-y-4 px-4 py-5">
                                    <p className={panelTitleClass}>
                                        At a glance
                                    </p>

                                    <div className="space-y-3 text-sm text-white/85">
                                        {recipeFacts.map((fact) => (
                                            <div
                                                key={fact.label}
                                                className="flex items-center justify-between rounded-2xl border border-white/10 bg-white/4 px-3 py-2.5"
                                            >
                                                <span className="text-white/58">
                                                    {fact.label}
                                                </span>
                                                <span>{fact.value}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className={panelCardClass}>
                                <CardContent className="space-y-3 px-4 py-5">
                                    <p className={panelTitleClass}>Tools</p>
                                    <div className="flex flex-wrap gap-2">
                                        {visibleTools &&
                                        visibleTools.length > 0 ? (
                                            visibleTools.map((tool) => (
                                                <Badge
                                                    key={tool.id}
                                                    className="rounded-full border-white/10 bg-white/6 text-white hover:bg-white/6"
                                                    variant="outline"
                                                >
                                                    {tool.title}
                                                </Badge>
                                            ))
                                        ) : (
                                            <Badge
                                                className="rounded-full border-white/10 bg-white/6 text-white hover:bg-white/6"
                                                variant="outline"
                                            >
                                                No special tools
                                            </Badge>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </aside>
                    </div>
                )}
            </div>
        </>
    );
}

RecipeSuggestionPage.layout = {
    breadcrumbs: [
        {
            title: 'Recipe Suggestions',
            href: suggestions(),
        },
    ],
};
