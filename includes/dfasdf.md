Strategy: Seamless WebGL Transitions via Static Bitmap Intermediaries

1. The Problem: The "Visual Void"

When a 3D renderer (e.g., a sofa configurator) is moved from a Product Gallery into a Modal Sheet, several technical hurdles cause a "flicker" or temporary disappearance of the model:

DOM Reparenting/Relocation: Moving an iframe or a heavy WebGL canvas in the DOM often triggers a context loss or a full reload of the iframe content.

Layout Shifts: Animating the size and position of a live WebGL renderer is computationally expensive. If the renderer is in a Shadow DOM, the browser's layout engine may hide it until the transition is complete to save resources.

Real-time Distortion: Attempting to "mirror" every frame during a resize results in pixel stretching or aliasing because the source buffer (the Gallery view) does not match the aspect ratio of the destination (the Configurator sheet) during the "in-between" animation frames.

2. The Solution: "Snapshot-and-Swap"

Instead of attempting to duplicate a live stream of frames, we use a single, high-quality ImageBitmap as a visual proxy.

Phase A: The Capture (Pre-Transition)

Immediately before the transition starts:

Freeze the Frame: Request the WebGL renderer to produce one final frame.

Generate Bitmap: Use createImageBitmap(canvas) to grab the current state of the sofa.

Overlay Proxy: Place a simple <img> or 2D <canvas> exactly over the Gallery renderer containing this bitmap.

Phase B: The Move (During Transition)

While the "Sofa" looks like it's still there (via the proxy):

Off-screen Relocation: Move the actual 3D Iframe to its new position (off-screen or hidden).

Prepare New State: Instruct the 3D renderer to resize its internal resolution to match the final "Configurator Sheet" dimensions.

Animate the Proxy: Animate the Static Proxy from the Gallery position to the Sheet position. Because it is a simple image, it will animate at a smooth 60fps without GPU stalls.

Phase C: The Handoff (Post-Transition)

Once the animation finishes and the 3D renderer has finished its internal resize:

Visibility Swap: Toggle the 3D Iframe to visibility: visible.

Fade Out Proxy: Gently fade out the static bitmap. The user perceives a continuous object, even though the underlying technology swapped from a 2D image back to a 3D WebGL context.

3. Technical Implementation Details

Capturing the "Last Good Frame"

To ensure the bitmap is available across the Shadow DOM/Iframe boundary:

// Inside the Iframe
async function getSnapshot() {
  // Ensure the buffer is preserved for capture
  const bitmap = await createImageBitmap(renderer.domElement);
  window.parent.postMessage({ type: 'TRANSITION_START_FRAME', bitmap }, '*', [bitmap]);
}


Why this fixes Distortion

Distortion happens when we try to map a square source to a rectangular destination in real-time. By using a static bitmap during the animation, we treat the sofa as a "sprite." We can use the CSS object-fit: cover or contain properties on the proxy image to ensure the sofa looks correct during the move, rather than stretching the WebGL buffer.

4. Summary of Benefits

Zero Latency: No "black screen" during DOM movement.

High Performance: The browser only has to animate a 2D quad instead of re-calculating 3D matrices during a layout shift.

User Experience: The transition feels "app-like" and premium, masking the heavy lifting required to initialize a 3D configurator.

