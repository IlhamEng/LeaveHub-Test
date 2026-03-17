<script setup lang="ts">
import type { LeaveStatus } from '@/types'

interface Props {
  status: LeaveStatus
}

const props = defineProps<Props>()

const statusConfig: Record<
  LeaveStatus,
  { label: string; dotColor: string; bgColor: string; textColor: string }
> = {
  pending: {
    label: 'pending',
    dotColor: '#f59e0b',
    bgColor: '#fef3c7',
    textColor: '#92400e',
  },
  approved: {
    label: 'approved',
    dotColor: '#10b981',
    bgColor: '#d1fae5',
    textColor: '#065f46',
  },
  rejected: {
    label: 'rejected',
    dotColor: '#ef4444',
    bgColor: '#fee2e2',
    textColor: '#991b1b',
  },
  canceled: {
    label: 'canceled',
    dotColor: '#9ca3af',
    bgColor: '#f3f4f6',
    textColor: '#6b7280',
  },
}

const config = statusConfig[props.status] ?? statusConfig['pending']
</script>

<template>
  <span
    class="status-badge"
    :style="{
      backgroundColor: config.bgColor,
      color: config.textColor,
    }"
  >
    <span
      class="status-dot"
      :style="{ backgroundColor: config.dotColor }"
    />
    {{ config.label }}
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.6;
  white-space: nowrap;
  letter-spacing: 0.01em;
}

.status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}
</style>
