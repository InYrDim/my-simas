import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@shared/lib/utils"
import { Slot } from "radix-ui"

const badgeVariants = cva(
  "group/badge inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        default:
          "border-primary/50 bg-primary/10 text-foreground [a]:hover:bg-primary/20",
        secondary:
          "border-border bg-muted text-muted-foreground [a]:hover:bg-border",
        destructive:
          "border-destructive/50 bg-destructive/10 text-foreground [a]:hover:bg-destructive/20",
        outline:
          "border-accent/60 bg-accent/10 text-foreground [a]:hover:bg-accent/20",
        ghost: "border-transparent text-foreground hover:bg-muted",
        link: "border-transparent text-primary underline-offset-4 hover:underline",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant = "default",
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot.Root : "span"

  return (
    <Comp
      data-slot="badge"
      data-variant={variant}
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
