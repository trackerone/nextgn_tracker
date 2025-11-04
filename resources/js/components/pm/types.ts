export interface UserSummary {
  id: number;
  name: string;
}

export interface MessageItem {
  id: number;
  conversation_id: number;
  sender_id: number;
  body_md: string;
  body_html: string;
  read_at: string | null;
  created_at: string;
  updated_at: string;
  sender?: UserSummary;
}

export interface ConversationItem {
  id: number;
  user_a_id: number;
  user_b_id: number;
  last_message_at: string | null;
  created_at: string;
  updated_at: string;
  user_a?: UserSummary;
  user_b?: UserSummary;
  last_message?: MessageItem;
  messages?: MessageItem[];
}

export interface StartConversationPayload {
  recipient_id: number;
  body_md: string;
}

export interface NewConversationResponse {
  conversation: ConversationItem;
  message: MessageItem;
}
