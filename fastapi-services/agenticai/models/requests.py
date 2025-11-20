from pydantic import BaseModel
from typing import Dict, Any, Optional, List

class ChatBotReq(BaseModel):
    persona: str
    query: str
    messages: list
    config_ids: list

class UserChatReq(BaseModel):
    user_query: str
    prior_context: Dict[str, Any] = {}
    session_id: Optional[str] = None
    user_id: Optional[int] = None