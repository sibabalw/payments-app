import { ReactNode } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';

interface Step {
    title: string;
    description?: string;
    content: ReactNode;
}

interface MultiStepFormProps {
    steps: Step[];
    currentStep: number;
    onStepChange: (step: number) => void;
    onNext?: () => void;
    onPrevious?: () => void;
    onSubmit?: () => void;
    canProceed?: boolean;
    showSkip?: boolean;
    onSkip?: () => void;
    isSubmitting?: boolean;
}

export function MultiStepForm({
    steps,
    currentStep,
    onStepChange,
    onNext,
    onPrevious,
    onSubmit,
    canProceed = true,
    showSkip = false,
    onSkip,
    isSubmitting = false,
}: MultiStepFormProps) {
    const isFirstStep = currentStep === 0;
    const isLastStep = currentStep === steps.length - 1;
    const progress = ((currentStep + 1) / steps.length) * 100;

    const handleNext = () => {
        if (canProceed && !isLastStep) {
            if (onNext) {
                onNext();
            } else {
                onStepChange(currentStep + 1);
            }
        }
    };

    const handlePrevious = () => {
        if (!isFirstStep) {
            if (onPrevious) {
                onPrevious();
            } else {
                onStepChange(currentStep - 1);
            }
        }
    };

    const handleStepClick = (stepIndex: number) => {
        // Allow going back to previous steps
        if (stepIndex <= currentStep) {
            onStepChange(stepIndex);
        }
    };

    return (
        <div className="space-y-6">
            {/* Progress Bar */}
            <div className="space-y-2">
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>Step {currentStep + 1} of {steps.length}</span>
                    <span>{Math.round(progress)}%</span>
                </div>
                <Progress value={progress} className="h-2" />
            </div>

            {/* Step Indicators */}
            <div className="flex items-center justify-between">
                {steps.map((step, index) => (
                    <div
                        key={index}
                        className="flex items-center flex-1"
                    >
                        <button
                            type="button"
                            onClick={() => handleStepClick(index)}
                            disabled={index > currentStep}
                            className={`
                                flex items-center justify-center w-8 h-8 rounded-full border-2 transition-colors
                                ${index < currentStep
                                    ? 'bg-primary text-primary-foreground border-primary'
                                    : index === currentStep
                                    ? 'bg-primary text-primary-foreground border-primary'
                                    : 'bg-background border-muted-foreground/25 text-muted-foreground cursor-not-allowed'
                                }
                            `}
                        >
                            {index < currentStep ? '✓' : index + 1}
                        </button>
                        {index < steps.length - 1 && (
                            <div
                                className={`
                                    flex-1 h-0.5 mx-2
                                    ${index < currentStep ? 'bg-primary' : 'bg-muted-foreground/25'}
                                `}
                            />
                        )}
                    </div>
                ))}
            </div>

            {/* Step Title and Description */}
            <div className="space-y-1">
                <h3 className="text-2xl font-bold">{steps[currentStep].title}</h3>
                {steps[currentStep].description && (
                    <p className="text-muted-foreground">{steps[currentStep].description}</p>
                )}
            </div>

            {/* Step Content */}
            <div className="min-h-[400px]">
                {steps[currentStep].content}
            </div>

            {/* Navigation Buttons */}
            <div className="flex items-center justify-between gap-3 pt-6 border-t">
                <div>
                    {showSkip && onSkip && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onSkip}
                            disabled={isSubmitting}
                        >
                            Skip for Now
                        </Button>
                    )}
                </div>
                <div className="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handlePrevious}
                        disabled={isFirstStep || isSubmitting}
                    >
                        <ChevronLeft className="h-4 w-4 mr-2" />
                        Previous
                    </Button>
                    {isLastStep ? (
                        <Button
                            type="button"
                            onClick={onSubmit}
                            disabled={!canProceed || isSubmitting}
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit'}
                            <ChevronRight className="h-4 w-4 ml-2" />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={handleNext}
                            disabled={!canProceed || isSubmitting}
                        >
                            Next
                            <ChevronRight className="h-4 w-4 ml-2" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
