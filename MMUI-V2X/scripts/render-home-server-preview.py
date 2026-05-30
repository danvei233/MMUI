import bpy
from datetime import datetime
from mathutils import Vector
from pathlib import Path

out_dir = Path(r"D:\mui3\MMUI-V2X")
stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
target = out_dir / f"home-server-material-preview-{stamp}.png"

for obj in bpy.context.scene.objects:
    obj.hide_viewport = False
    obj.hide_render = False

for obj in list(bpy.context.scene.objects):
    if obj.type in {"LIGHT", "CAMERA"}:
        bpy.data.objects.remove(obj, do_unlink=True)

world = bpy.data.worlds.new("MMUI clean preview world")
bpy.context.scene.world = world
world.use_nodes = True
background = world.node_tree.nodes.get("Background")
if background:
    background.inputs["Color"].default_value = (1.0, 1.0, 1.0, 1.0)
    background.inputs["Strength"].default_value = 0.8

bpy.ops.object.light_add(type="AREA", location=(-3.8, -4.2, 6.2))
key = bpy.context.object
key.name = "MMUI preview key"
key.data.energy = 520
key.data.size = 6.8

bpy.ops.object.light_add(type="AREA", location=(4.2, 2.8, 4.8))
fill = bpy.context.object
fill.name = "MMUI preview fill"
fill.data.energy = 220
fill.data.size = 8.2

bpy.ops.object.light_add(type="POINT", location=(-1.8, -2.4, 2.2))
rim = bpy.context.object
rim.name = "MMUI preview glass glow"
rim.data.energy = 120
rim.data.color = (0.46, 0.7, 1.0)

bpy.ops.object.camera_add(location=(4.0, -4.9, 3.15))
camera = bpy.context.object
bpy.context.scene.camera = camera
direction = Vector((0, 0, 0.75)) - camera.location
camera.rotation_euler = direction.to_track_quat("-Z", "Y").to_euler()

bpy.context.scene.render.engine = "CYCLES"
bpy.context.scene.cycles.samples = 96
bpy.context.scene.cycles.use_denoising = True
bpy.context.scene.cycles.transparent_max_bounces = 10
bpy.context.scene.cycles.transmission_bounces = 10
bpy.context.scene.render.resolution_x = 720
bpy.context.scene.render.resolution_y = 620
bpy.context.scene.world.color = (1.0, 1.0, 1.0)
bpy.context.scene.render.film_transparent = False
bpy.context.scene.view_settings.view_transform = "Standard"
bpy.context.scene.view_settings.look = "Medium High Contrast"
bpy.context.scene.view_settings.exposure = -0.12
bpy.context.scene.view_settings.gamma = 1

bpy.ops.render.render(write_still=True)
bpy.data.images["Render Result"].save_render(filepath=str(target))
print(f"Rendered material preview -> {target}")
